<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTemplateRequest;
use App\Http\Requests\UpdateTemplateRequest;
use App\Models\Template;
use App\Services\VariableExtractor;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Template::query()->withCount('variables')->with('versions');

        if ($request->user()->role === 'user') {
            $query->where('status', 'published');
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        if ($request->filled('status') && $request->user()->role !== 'user') {
            $query->where('status', $request->string('status'));
        }

        $templates = $query->latest()->get()->map(fn (Template $template) => $this->formatTemplate($template));

        return response()->json($templates);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTemplateRequest $request)
    {
        $data = $request->validated();
        $path = $request->file('file')->store('templates', 'public');

        $template = Template::create([
            'name' => $data['name'],
            'category' => $data['category'] ?? '',
            'format' => $data['format'],
            'status' => 'draft',
            'file_path' => $path,
            'tags' => $data['tags'] ?? [],
            'created_by' => $request->user()->id,
        ]);

        $template->versions()->create([
            'version_number' => 1,
            'file_path' => $path,
        ]);

        return response()->json($this->formatTemplate($template->load('versions')), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Template $template)
    {
        $template->load(['versions', 'variables', 'creator']);

        return response()->json($this->formatTemplate($template));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTemplateRequest $request, Template $template)
    {
        $template->update($request->validated());

        return response()->json($this->formatTemplate($template->fresh('versions')));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Template $template)
    {
        if ($request->user()->role !== 'admin') {
            abort(403, 'Удалять шаблоны может только администратор.');
        }

        $template->delete();

        return response()->json(null, 204);
    }

    /**
     * Upload a new version of an existing template without overwriting old ones.
     */
    public function storeVersion(Request $request, Template $template)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:docx,pdf', 'max:20480'],
        ]);

        $path = $request->file('file')->store('templates', 'public');

        $nextVersionNumber = $template->versions()->max('version_number') + 1;

        $version = $template->versions()->create([
            'version_number' => $nextVersionNumber,
            'file_path' => $path,
        ]);

        $template->update(['file_path' => $path]);

        return response()->json($version, 201);
    }

    /**
     * Recognise {{placeholder}} markup in the current version's file and
     * register any newly found keys as draft variables (type "text" by default).
     */
    public function extractVariables(Template $template, VariableExtractor $extractor)
    {
        $version = $template->currentVersion;
        if (! $version) {
            abort(422, 'У шаблона нет ни одной загруженной версии.');
        }

        $errors = $extractor->validate($version->full_path, $template->format);
        if ($errors !== []) {
            return response()->json(['errors' => $errors], 422);
        }

        $keys = $extractor->extractKeys($version->full_path, $template->format);
        $existingKeys = $template->variables()->pluck('key')->all();

        foreach ($keys as $key) {
            if (in_array($key, $existingKeys, true)) {
                continue;
            }

            $template->variables()->create([
                'key' => $key,
                'label' => $key,
                'type' => 'text',
                'required' => false,
            ]);
        }

        return response()->json($this->formatTemplate($template->fresh('variables')));
    }

    /**
     * Publish a template, making it available to regular users.
     */
    public function publish(Template $template, VariableExtractor $extractor)
    {
        $version = $template->currentVersion;
        if (! $version) {
            abort(422, 'У шаблона нет ни одной загруженной версии.');
        }

        $errors = $extractor->validate($version->full_path, $template->format);
        if ($errors !== []) {
            return response()->json(['errors' => $errors], 422);
        }

        $template->update([
            'status' => 'published',
        ]);

        $version->update(['published_at' => now()]);

        return response()->json($this->formatTemplate($template->fresh('versions')));
    }

    /**
     * Shape a template into the JSON contract expected by the frontend
     * (see frontend/src/api/backendAdapters.ts on the frontend branch).
     */
    private function formatTemplate(Template $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'category' => $template->category ?? '',
            'format' => $template->format,
            'status' => $template->status,
            'tags' => $template->tags ?? [],
            'created_at' => $template->created_at,
            'variables_count' => $template->variables_count ?? $template->variables()->count(),
            'versions' => $template->versions->map(fn ($version) => [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'file_path' => $version->file_path,
                'published_at' => $version->published_at,
            ])->values(),
            'variables' => $template->relationLoaded('variables')
                ? $template->variables->map(fn ($variable) => [
                    'id' => $variable->id,
                    'key' => $variable->key,
                    'label' => $variable->label,
                    'type' => $variable->type,
                    'required' => $variable->required,
                    'default_value' => $variable->default_value,
                    'hint' => $variable->hint,
                    'options' => $variable->options,
                ])->values()
                : [],
            'creator' => $template->relationLoaded('creator') && $template->creator
                ? ['id' => $template->creator->id, 'name' => $template->creator->name]
                : null,
        ];
    }
}
