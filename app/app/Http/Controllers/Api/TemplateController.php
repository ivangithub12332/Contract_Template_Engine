<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTemplateRequest;
use App\Http\Requests\UpdateTemplateRequest;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Template::query()->with('currentVersion');

        if ($request->user()->role === 'user') {
            $query->where('status', 'published');
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        if ($request->filled('status') && $request->user()->role !== 'user') {
            $query->where('status', $request->string('status'));
        }

        return response()->json($query->latest()->paginate(20));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTemplateRequest $request)
    {
        $data = $request->validated();
        $file = $request->file('file');
        $path = $file->store('templates', 'public');

        $template = Template::create([
            'name' => $data['name'],
            'category' => $data['category'] ?? null,
            'format' => $data['format'],
            'status' => 'draft',
            'file_path' => $path,
            'tags' => $data['tags'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $template->versions()->create([
            'version_number' => 1,
            'file_path' => $path,
        ]);

        return response()->json($template->load('currentVersion'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Template $template)
    {
        return response()->json($template->load(['versions', 'variables', 'creator']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTemplateRequest $request, Template $template)
    {
        $template->update($request->validated());

        return response()->json($template->fresh());
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
     * Publish a template, making it available to regular users.
     */
    public function publish(Template $template)
    {
        $template->update([
            'status' => 'published',
        ]);

        $template->currentVersion?->update(['published_at' => now()]);

        return response()->json($template->fresh('currentVersion'));
    }
}
