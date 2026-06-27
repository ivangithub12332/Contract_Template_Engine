<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVariableRequest extends FormRequest
{
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
            'key' => [
                'required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_]+$/',
                Rule::unique('variables')->where('template_id', $this->route('template')->id),
            ],
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:text,textarea,number,currency,date,select,boolean,table'],
            'required' => ['sometimes', 'boolean'],
            'options' => ['nullable', 'array'],
            'default_value' => ['nullable', 'string'],
            'hint' => ['nullable', 'string', 'max:255'],
        ];
    }
}
