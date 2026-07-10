<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTemplateRequest extends FormRequest
{
    public const MAX_FILE_SIZE_KB = 51200;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'methodologist'], true);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'format' => ['required', 'in:docx,pdf'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:255'],
            'file' => ['required', 'file', 'mimes:docx,pdf', 'max:'.self::MAX_FILE_SIZE_KB],
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'Размер файла не должен превышать 50 МБ.',
            'file.uploaded' => 'Не удалось загрузить файл. Максимальный размер - 50 МБ.',
        ];
    }
}
