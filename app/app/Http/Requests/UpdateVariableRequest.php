<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVariableRequest extends FormRequest
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
            'label' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'in:text,textarea,number,currency,date,select,boolean,table'],
            'required' => ['sometimes', 'boolean'],
            'options' => ['nullable', 'array'],
            'default_value' => ['nullable', 'string'],
            'hint' => ['nullable', 'string', 'max:255'],
        ];
    }
}
