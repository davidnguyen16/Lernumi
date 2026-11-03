<?php

namespace App\Services;

use App\Models\Tool;
use App\Models\ToolVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class ToolPackageService
{
    public function upload(User $user, Tool $tool, UploadedFile $file, ?string $notes, bool $activate = true): ToolVersion
    {
        if ($file->getSize() > config('tools.max_archive_bytes')) {
            $this->invalid('Packages must not exceed 25 MiB.');
        }
        $zip = new ZipArchive;
        if ($zip->open($file->getRealPath()) !== true) {
            $this->invalid('Upload a valid ZIP archive.');
        }
        $prefix = null;
        $archiveKey = null;
        try {
            if ($zip->numFiles > config('tools.max_package_files')) $this->invalid('Packages may contain at most 2,000 entries.');
            $seen = [];
            $unpacked = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $path = rtrim($stat['name'], '/');
                if (! self::safePath($path) || isset($seen[$stat['name']])) $this->invalid('The archive contains unsafe or duplicate paths.');
                $seen[$stat['name']] = true;
                $zip->getExternalAttributesIndex($i, $opsys, $attributes);
                if ((($attributes >> 16) & 0170000) === 0120000 || ($stat['encryption_method'] ?? 0) !== 0) $this->invalid('Symlinks and encrypted entries are not supported.');
                if ($stat['size'] > config('tools.max_file_bytes')) $this->invalid('Each package file must be at most 20 MiB.');
                $unpacked += $stat['size'];
                if ($unpacked > config('tools.max_unpacked_bytes')) $this->invalid('Expanded package exceeds 100 MiB.');
            }
            $manifestBytes = $zip->getFromName('tool.json', 16385);
            if ($manifestBytes === false || strlen($manifestBytes) > 16384) $this->invalid('A tool.json manifest is required at the ZIP root.');
            try { $manifest = json_decode($manifestBytes, true, 32, JSON_THROW_ON_ERROR); }
            catch (\JsonException $exception) { $this->invalid('tool.json must be valid JSON.'); }
            if (! is_array($manifest) || array_diff(array_keys($manifest), ['schemaVersion', 'code', 'version', 'entry'])
                || ($manifest['schemaVersion'] ?? null) !== 1 || ($manifest['code'] ?? null) !== $tool->code
                || ! is_string($manifest['version'] ?? null) || strlen($manifest['version']) > 80
                || ! preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/', $manifest['version'])
                || ! is_string($manifest['entry'] ?? null) || ! self::safePath($manifest['entry'])
                || strlen($manifest['entry']) > 300 || ! preg_match('/\.html?$/i', $manifest['entry'])
                || $zip->locateName($manifest['entry']) === false) {
                $this->invalid('Manifest must specify schemaVersion 1, the matching code, a semantic version and an existing HTML entry.');
            }

            return DB::transaction(function () use ($user, $tool, $file, $notes, $activate, $zip, $manifest, $unpacked, &$prefix, &$archiveKey): ToolVersion {
                $tool = Tool::query()->whereKey($tool->id)->lockForUpdate()->firstOrFail();
                if ($tool->versions()->where('version', $manifest['version'])->exists()) $this->invalid('This immutable version already exists. Use a new version number.');
                $versionId = (string) Str::uuid();
                $prefix = "tool-assets/{$tool->id}/{$versionId}";
                $archiveKey = "tool-packages/{$tool->id}/{$versionId}.zip";
                $stream = fopen($file->getRealPath(), 'rb');
                try {
                    if (! Storage::disk('local')->put($archiveKey, $stream)) throw new \RuntimeException('Could not store package archive.');
                } finally { if (is_resource($stream)) fclose($stream); }
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    if (str_ends_with($name, '/')) continue;
                    $bytes = $zip->getFromIndex($i, config('tools.max_file_bytes') + 1);
                    if ($bytes === false || strlen($bytes) > config('tools.max_file_bytes')) $this->invalid('Could not safely expand the archive.');
                    if (! Storage::disk('local')->put("{$prefix}/{$name}", $bytes)) throw new \RuntimeException('Could not store package file.');
                }
                $version = new ToolVersion([
                    'tool_id' => $tool->id, 'created_by_id' => $user->id,
                    'version' => $manifest['version'], 'entry_path' => $manifest['entry'], 'manifest' => $manifest,
                    'archive_key' => $archiveKey, 'storage_prefix' => $prefix,
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                    'compressed_size' => $file->getSize(), 'unpacked_size' => $unpacked,
                    'file_count' => $zip->numFiles, 'release_notes' => $notes,
                ]);
                $version->id = $versionId;
                $version->save();
                if ($activate) $tool->update(['active_version_id' => $versionId]);

                return $version;
            });
        } catch (\Throwable $exception) {
            if ($prefix !== null) Storage::disk('local')->deleteDirectory($prefix);
            if ($archiveKey !== null) Storage::disk('local')->delete($archiveKey);
            throw $exception;
        } finally {
            $zip->close();
        }
    }

    public static function safePath(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || preg_match('/[\\\\:\x00-\x1f\x7f]/', $path)) return false;
        foreach (explode('/', $path) as $part) {
            if ($part === '' || in_array(strtolower($part), ['.', '..', '.git', 'node_modules'], true) || str_starts_with(strtolower($part), '.env')) return false;
        }

        return true;
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['package' => $message]);
    }
}
