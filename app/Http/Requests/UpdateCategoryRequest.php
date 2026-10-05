<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')
                    ->where('tenant_id', $this->user()?->tenant_id)
                    ->whereNull('deleted_at')
                    ->ignore($this->route('id')),
            ],
            'description' => ['sometimes', 'required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'        => 'Le nom de la catégorie est obligatoire.',
            'name.string'          => 'Le nom de la catégorie doit être une chaîne de caractères.',
            'name.max'             => 'Le nom de la catégorie ne peut pas dépasser 255 caractères.',
            'name.unique'          => 'Cette catégorie existe déjà.',
            'description.required' => 'La description de la catégorie est obligatoire.',
            'description.string'   => 'La description de la catégorie doit être une chaîne de caractères.',
            'description.max'      => 'La description ne peut pas dépasser 1000 caractères.',
        ];
    }
}
