<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => strtoupper((string) ($this->input('currency') ?: config('subscriptions.default_currency', 'XOF'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', Rule::exists('tenants', 'id')->whereNull('deleted_at')],
            'plan'      => ['required', 'string', 'max:100'],
            'amount'    => ['required', 'numeric', 'min:0'],
            'currency'  => ['required', 'string', 'size:3'],
            'starts_at' => ['required', 'date'],
            'ends_at'   => ['required', 'date', 'after_or_equal:starts_at'],
            'notes'     => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'tenant_id.required'     => "L'entreprise est obligatoire.",
            'tenant_id.exists'       => "L'entreprise sélectionnée est invalide.",
            'plan.required'          => 'La formule est obligatoire.',
            'amount.required'        => 'Le montant est obligatoire.',
            'amount.min'             => 'Le montant ne peut pas être négatif.',
            'starts_at.required'     => 'La date de début est obligatoire.',
            'ends_at.required'       => 'La date de fin est obligatoire.',
            'ends_at.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ];
    }
}
