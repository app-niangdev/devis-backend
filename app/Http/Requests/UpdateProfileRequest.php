<?php

namespace App\Http\Requests;

use App\Support\SenegalPhone;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Profil en libre-service. Le numéro de connexion (phone_one) ne peut être changé
 * que par l'administrateur.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone_two')) {
            $this->merge(['phone_two' => SenegalPhone::clean($this->input('phone_two'))]);
        }
    }

    public function rules(): array
    {
        return [
            'phone_two'        => ['nullable', 'string', 'regex:' . SenegalPhone::PATTERN],
            'address'          => ['nullable', 'string', 'max:255'],
            'current_password' => ['required_with:password', 'nullable', 'string'],
            'password'         => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone_two.regex'                 => 'Le numéro doit comporter 9 chiffres et commencer par 70, 71, 75, 76, 77 ou 78.',
            'current_password.required_with'  => 'Veuillez saisir votre mot de passe actuel.',
            'password.min'                    => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed'              => 'La confirmation du mot de passe ne correspond pas.',
        ];
    }
}
