<?php

namespace App\Http\Requests;

use App\Support\SenegalPhone;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Inscription d'un artisan depuis l'application : son entreprise et son compte gestionnaire.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => SenegalPhone::clean($this->input('phone')),
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
            'company_name' => trim((string) $this->input('company_name')),
            'trade' => trim((string) $this->input('trade')) ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'first_name'            => ['required', 'string', 'min:2', 'max:100'],
            'last_name'             => ['required', 'string', 'min:2', 'max:100'],
            'company_name'          => ['required', 'string', 'min:2', 'max:150'],
            'trade'                 => ['nullable', 'string', 'max:100'],
            'phone'                 => ['required', 'string', 'regex:' . SenegalPhone::PATTERN],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required'            => 'Le prénom est obligatoire.',
            'first_name.min'                 => 'Le prénom doit contenir au moins 2 caractères.',
            'last_name.required'             => 'Le nom est obligatoire.',
            'last_name.min'                  => 'Le nom doit contenir au moins 2 caractères.',
            'company_name.required'          => 'Le nom de l\'entreprise est obligatoire.',
            'company_name.min'               => 'Le nom de l\'entreprise doit contenir au moins 2 caractères.',
            'phone.required'                 => 'Le numéro de téléphone est obligatoire.',
            'phone.regex'                    => 'Le numéro doit comporter 9 chiffres et commencer par 70, 71, 75, 76, 77 ou 78.',
            'password.required'              => 'Le mot de passe est obligatoire.',
            'password.min'                   => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed'             => 'La confirmation du mot de passe ne correspond pas.',
            'password_confirmation.required' => 'La confirmation du mot de passe est obligatoire.',
        ];
    }
}
