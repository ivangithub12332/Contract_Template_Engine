<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateDocumentRequest;
use App\Models\Document;
use App\Models\Template;
use App\Models\Variable;
use App\Services\DocumentGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DocumentController extends Controller
{
    /**
     * List documents with history filters. Plain users only see their own.
     */
    public function index(Request $request)
    {
        $query = Document::query()->with(['templateVersion.template', 'author']);

        if ($request->user()->role === 'user') {
            $query->where('author_id', $request->user()->id);
        } elseif ($request->filled('author_id')) {
            $query->where('author_id', $request->integer('author_id'));
        }

        if ($request->filled('template_id')) {
            $query->whereHas(
                'templateVersion',
                fn ($q) => $q->where('template_id', $request->integer('template_id'))
            );
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date('date_to'));
        }

        $documents = $query->latest()->get()->map(fn (Document $document) => $this->formatDocumentListItem($document));

        return response()->json($documents);
    }

    /**
     * Generate a document from a published template and the submitted form values.
     */
    public function store(GenerateDocumentRequest $request, Template $template, DocumentGenerator $generator)
    {
        if ($template->status !== 'published') {
            abort(422, 'Документы можно создавать только по опубликованным шаблонам.');
        }

        $version = $template->currentVersion;
        if (! $version) {
            abort(422, 'У шаблона нет ни одной загруженной версии.');
        }

        $values = $request->validated()['values'];
        $variables = $template->variables;

        $this->validateValues($variables, $values);

        $relativePath = $generator->generate($version, $variables, $values);

        $document = Document::create([
            'template_version_id' => $version->id,
            'author_id' => $request->user()->id,
            'file_path' => $relativePath,
            'metadata' => ['template_name' => $template->name],
        ]);

        foreach ($variables as $variable) {
            $document->values()->create([
                'variable_id' => $variable->id,
                'value' => $values[$variable->key] ?? $variable->default_value,
            ]);
        }

        $document->load(['templateVersion.template', 'author', 'values.variable']);

        return response()->json($this->formatDocument($document), 201);
    }

    /**
     * Show a document together with the values it was generated from
     * (also used by the frontend to prefill the form for "recreate").
     */
    public function show(Document $document)
    {
        $this->authorizeAccess($document);

        $document->load(['templateVersion.template', 'author', 'values.variable']);

        return response()->json($this->formatDocument($document));
    }

    public function download(Document $document)
    {
        $this->authorizeAccess($document);

        return response()->download($document->full_path, $document->download_name);
    }

    private function authorizeAccess(Document $document): void
    {
        $user = request()->user();
        if ($user->role === 'user' && $document->author_id !== $user->id) {
            abort(403, 'Доступ только к собственным документам.');
        }
    }

    /**
     * @param Collection<int, Variable> $variables
     */
    private function validateValues(Collection $variables, array $values): void
    {
        $errors = [];

        foreach ($variables as $variable) {
            $value = $values[$variable->key] ?? null;

            if ($variable->required && ($value === null || $value === '')) {
                $errors[$variable->key] = ["Поле \"{$variable->label}\" обязательно для заполнения."];

                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $message = match ($variable->type) {
                'number', 'currency' => is_numeric($value) ? null : 'должно быть числом',
                'date' => strtotime((string) $value) !== false ? null : 'должно быть корректной датой',
                'boolean' => in_array($value, [true, false, 0, 1, '0', '1'], true) ? null : 'должно быть true или false',
                'select' => in_array($value, $variable->getOptions(), false) ? null : 'недопустимое значение списка',
                'table' => is_array($value) ? null : 'должно быть списком строк',
                default => null,
            };

            if ($message !== null) {
                $errors[$variable->key] = ["Поле \"{$variable->label}\" {$message}."];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function formatDocument(Document $document): array
    {
        $values = $document->values->map(fn ($value) => [
            'variable_key' => $value->variable->key,
            'value' => $value->value,
        ])->values();

        return [
            ...$this->formatDocumentListItem($document),
            'values' => $values,
        ];
    }

    private function formatDocumentListItem(Document $document): array
    {
        return [
            'id' => $document->id,
            'template_id' => $document->templateVersion->template_id,
            'template_name' => $document->templateVersion->template->name,
            'author_name' => $document->author->name,
            'file_path' => $document->file_path,
            'created_at' => $document->created_at,
        ];
    }
}
