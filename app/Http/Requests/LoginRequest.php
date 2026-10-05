<?php

namespace App\Http\Requests;

use App\Support\SenegalPhone;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Gestionnaire : téléphone + mot de passe. Administrateur : e-mail + mot de passe.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge(['phone' => SenegalPhone::clean($this->input('phone'))]);
        }
    }

    public function rules(): array
    {
        return [
            'phone'    => ['required_without:email', 'nullable', 'string', 'regex:' . SenegalPhone::PATTERN],
            'email'    => ['required_without:phone', 'nullable', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required_without' => 'Le numéro de téléphone est obligatoire.',
            'phone.regex'            => 'Le numéro doit comporter 9 chiffres et commencer par 70, 71, 75, 76, 77 ou 78.',
            'email.required_without' => 'Le numéro de téléphone est obligatoire.',
            'email.email'            => "L'adresse e-mail n'est pas valide.",
            'password.required'      => 'Le mot de passe est obligatoire.',
        ];
    }
}
