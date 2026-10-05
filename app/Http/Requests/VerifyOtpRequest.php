<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'challenge_token' => ['required', 'string', 'size:64'],
            'code'            => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'challenge_token.required' => 'La demande est introuvable. Recommencez la procédure.',
            'challenge_token.size'     => 'La demande est introuvable. Recommencez la procédure.',
            'code.required'            => 'Saisissez le code reçu sur WhatsApp.',
            'code.regex'               => 'Le code comporte 6 chiffres.',
        ];
    }
}
