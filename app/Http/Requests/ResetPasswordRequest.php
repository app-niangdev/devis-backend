<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'                  => ['required', 'email'],
            'code'                   => ['required', 'string'],
            'password'               => ['required', 'string', 'min:6', 'confirmed'],
            'password_confirmation'  => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'                  => "L'adresse e-mail est obligatoire.",
            'email.email'                      => "L'adresse e-mail n'est pas valide.",
            'code.required'                   => 'Le code de vérification est obligatoire.',
            'password.required'               => 'Le nouveau mot de passe est obligatoire.',
            'password.min'                     => 'Le mot de passe doit contenir au moins 6 caractères.',
            'password.confirmed'              => 'La confirmation du mot de passe ne correspond pas.',
            'password_confirmation.required'  => 'La confirmation du mot de passe est obligatoire.',
        ];
    }
}
