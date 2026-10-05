<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\TenantProfileRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Informations de l'entreprise modifiées par son gestionnaire
 * (ni le code, ni le statut, ni l'abonnement).
 */
class UpdateCompanyRequest extends FormRequest
{
    use TenantProfileRules;

    public function authorize(): bool
    {
        return (bool) $this->user()?->isManager();
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeColors();
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
        ] + $this->tenantProfileRules();
    }

    public function messages(): array
    {
        return [
            'name.required' => "Le nom de l'entreprise est obligatoire.",
        ] + $this->tenantProfileMessages();
    }
}
