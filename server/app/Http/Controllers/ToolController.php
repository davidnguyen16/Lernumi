<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tools\ToolRequest;
use App\Http\Resources\ToolResource;
use App\Http\Resources\ToolVersionResource;
use App\Models\Tool;
use App\Models\ToolVersion;
use App\Services\AccessService;
use App\Services\ToolPackageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ToolController
{
    public function __construct(private AccessService $access, private ToolPackageService $packages) {}

    public function index(Request $request)
    {
        if ($request->is('api/v1/admin/*')) $this->admin($request);
        $request->validate(['search' => ['sometimes', 'nullable', 'string', 'max:200'], 'per_page' => ['sometimes', 'integer', 'between:1,100'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $tools = Tool::query()->with('activeVersion');
        if ($this->access->isAdmin($request->user())) $tools->withCount(['versions', 'assessments', 'workSessions']);
        if (! $this->access->isAdmin($request->user())) $tools->whereNotNull('active_version_id');
        if ($request->filled('search')) $tools->whereRaw('strpos(lower(name), lower(?)) > 0', [$request->query('search')]);

        return ToolResource::collection($tools->orderBy('name')->orderBy('id')->paginate((int) $request->query('per_page', 15))->withQueryString());
    }

    public function store(ToolRequest $request)
    {
        $this->admin($request);
        $tool = DB::transaction(function () use ($request): Tool {
            $tool = Tool::query()->create([
                'code' => $request->validated('code'), 'name' => $request->validated('name'),
                'description' => $request->validated('description'), 'code_base_location' => $request->validated('codeBaseLocation'),
            ]);
            if ($request->hasFile('package')) $this->packages->upload($request->user(), $tool, $request->file('package'), $request->validated('releaseNotes'), (bool) $request->validated('activate', true));

            return $tool->fresh('activeVersion');
        });

        return (new ToolResource($tool))->response()->setStatusCode(201);
    }

    public function show(Request $request, Tool $tool)
    {
        if ($request->is('api/v1/admin/*')) $this->admin($request);
        abort_unless($this->access->isAdmin($request->user()) || $tool->active_version_id !== null, 404);

        if ($this->access->isAdmin($request->user())) $tool->loadCount(['versions', 'assessments', 'workSessions']);
        return new ToolResource($tool->load($this->access->isAdmin($request->user()) ? ['activeVersion', 'versions'] : ['activeVersion']));
    }

    public function update(ToolRequest $request, Tool $tool)
    {
        $this->admin($request);
        $data = $request->validated();
        $attributes = [];
        foreach (['name' => 'name', 'description' => 'description', 'codeBaseLocation' => 'code_base_location'] as $field => $column) {
            if (array_key_exists($field, $data)) $attributes[$column] = $data[$field];
        }
        $tool->update($attributes);

        return new ToolResource($tool->fresh('activeVersion'));
    }

    public function destroy(Request $request, Tool $tool)
    {
        $this->admin($request);
        abort_if($tool->versions()->exists() || DB::table('assessment_allow_tools')->where('tool_id', $tool->id)->exists(), 409, 'Versioned or assessment-linked tools are retained.');
        $tool->delete();

        return response()->noContent();
    }

    public function versions(Request $request, Tool $tool)
    {
        $this->admin($request);

        return ToolVersionResource::collection($tool->versions()->orderByDesc('created_at')->orderBy('id')->paginate(20));
    }

    public function upload(Request $request, Tool $tool)
    {
        $this->admin($request);
        $data = $request->validate(['package' => ['required', 'file', 'max:25600'], 'releaseNotes' => ['sometimes', 'nullable', 'string', 'max:2000'], 'activate' => ['sometimes', 'boolean']]);
        $version = $this->packages->upload($request->user(), $tool, $request->file('package'), $data['releaseNotes'] ?? null, (bool) ($data['activate'] ?? true));

        return (new ToolVersionResource($version))->response()->setStatusCode(201);
    }

    public function activate(Request $request, Tool $tool, ToolVersion $version)
    {
        $this->admin($request);
        abort_unless($version->tool_id === $tool->id, 404);
        $tool->update(['active_version_id' => $version->id]);

        return new ToolResource($tool->fresh('activeVersion'));
    }

    public function download(Request $request, Tool $tool, ToolVersion $version)
    {
        $this->admin($request);
        abort_unless($version->tool_id === $tool->id, 404);

        return Storage::disk('local')->download($version->archive_key, "{$tool->code}-{$version->version}.zip");
    }

    private function admin(Request $request): void { abort_unless($this->access->isAdmin($request->user()), 403); }
}
