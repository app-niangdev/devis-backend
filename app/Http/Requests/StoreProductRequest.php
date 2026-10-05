<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'name')
                    ->where('tenant_id', $this->user()?->tenant_id)
                    ->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'unit_price'  => ['required', 'numeric', 'min:0'],
            'image'       => ['nullable', 'image', 'max:2048'],
            'image_url'   => ['nullable', 'url', 'max:2048'],
            'available'   => ['sometimes', 'boolean'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where('tenant_id', $this->user()?->tenant_id)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'         => 'Le nom du produit est obligatoire.',
            'name.string'           => 'Le nom du produit doit être une chaîne de caractères.',
            'name.max'              => 'Le nom du produit ne peut pas dépasser 255 caractères.',
            'name.unique'           => 'Ce produit existe déjà.',
            'description.string'    => 'La description du produit doit être une chaîne de caractères.',
            'description.max'       => 'La description ne peut pas dépasser 1000 caractères.',
            'unit_price.required'   => 'Le prix unitaire est obligatoire.',
            'unit_price.numeric'    => 'Le prix unitaire doit être un nombre.',
            'unit_price.min'        => 'Le prix unitaire ne peut pas être négatif.',
            'image.image'           => "Le fichier doit être une image.",
            'image.max'             => "L'image ne peut pas dépasser 2 Mo.",
            'image_url.url'         => "L'URL de l'image est invalide.",
            'image_url.max'         => "L'URL de l'image ne peut pas dépasser 2048 caractères.",
            'available.boolean'     => 'La disponibilité doit être vraie ou fausse.',
            'category_id.required'  => 'La catégorie est obligatoire.',
            'category_id.integer'   => 'La catégorie est invalide.',
            'category_id.exists'    => "Cette catégorie n'existe pas.",
        ];
    }
}
