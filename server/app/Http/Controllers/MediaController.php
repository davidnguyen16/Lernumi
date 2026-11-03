<?php

namespace App\Http\Controllers;

use App\Http\Resources\InstitutionResource;
use App\Models\Institution;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController
{
    public function __construct(private AccessService $access) {}

    public function storeLogo(Request $request, Institution $institution)
    {
        $this->access->authorize($request->user(), 'institution.settings.update', $institution);
        $request->validate(['logo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        $old = $institution->logo_key;
        $key = $request->file('logo')->store("institution-logos/{$institution->id}", 'local');
        abort_unless($key, 503, 'Could not store the logo.');
        try {
            $institution->forceFill(['logo_key' => $key])->save();
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($key);
            throw $exception;
        }
        if ($old) Storage::disk('local')->delete($old);

        return new InstitutionResource($institution);
    }

    public function showLogo(Request $request, Institution $institution)
    {
        abort_unless($institution->logo_key && Storage::disk('local')->exists($institution->logo_key), 404);

        return response()->file(Storage::disk('local')->path($institution->logo_key), ['X-Content-Type-Options' => 'nosniff']);
    }

    public function destroyLogo(Request $request, Institution $institution)
    {
        $this->access->authorize($request->user(), 'institution.settings.update', $institution);
        $old = $institution->logo_key;
        $institution->forceFill(['logo_key' => null])->save();
        if ($old) Storage::disk('local')->delete($old);

        return response()->noContent();
    }
}
