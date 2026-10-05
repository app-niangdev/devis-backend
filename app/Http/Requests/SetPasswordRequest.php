<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Nouveau mot de passe après un code OTP valide (première connexion ou mot de passe oublié).
 */
class SetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reset_token'           => ['required', 'string', 'size:64'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'reset_token.required'           => 'La demande a expiré. Recommencez la procédure.',
            'reset_token.size'               => 'La demande a expiré. Recommencez la procédure.',
            'password.required'              => 'Le nouveau mot de passe est obligatoire.',
            'password.min'                   => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed'             => 'La confirmation du mot de passe ne correspond pas.',
            'password_confirmation.required' => 'La confirmation du mot de passe est obligatoire.',
        ];
    }
}
