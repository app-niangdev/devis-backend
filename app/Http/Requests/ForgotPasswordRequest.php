<?php

namespace App\Http\Requests;

use App\Support\SenegalPhone;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Gestionnaire : numéro de téléphone (code OTP sur WhatsApp). Administrateur : e-mail (code par e-mail).
 */
class ForgotPasswordRequest extends FormRequest
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
            'phone' => ['required_without:email', 'nullable', 'string', 'regex:' . SenegalPhone::PATTERN],
            'email' => ['required_without:phone', 'nullable', 'email'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required_without' => 'Le numéro de téléphone est obligatoire.',
            'phone.regex'            => 'Le numéro doit comporter 9 chiffres et commencer par 70, 71, 75, 76, 77 ou 78.',
            'email.required_without' => "L'adresse e-mail est obligatoire.",
            'email.email'            => "L'adresse e-mail n'est pas valide.",
        ];
    }
}
