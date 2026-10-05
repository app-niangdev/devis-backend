<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\TenantProfileRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends FormRequest
{
    use TenantProfileRules;

    public function authorize(): bool
    {
        return Gate::allows('admin');
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeColors();
    }

    public function rules(): array
    {
        return [
            'name'         => ['sometimes', 'required', 'string', 'max:255'],
            'code_website' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('tenants', 'code_website')->ignore($this->route('id')),
            ],
            'state'        => ['nullable', 'boolean'],
            'user_id'      => ['nullable', 'exists:users,id'],
        ] + $this->tenantProfileRules();
    }

    public function messages(): array
    {
        return [
            'name.required'         => "Le nom de l'entreprise est obligatoire.",
            'code_website.required' => "Le code de l'entreprise est obligatoire.",
            'code_website.unique'   => 'Ce code est déjà utilisé.',
            'state.boolean'         => 'Le statut doit être vrai ou faux.',
            'user_id.exists'        => "L'utilisateur sélectionné est invalide.",
        ] + $this->tenantProfileMessages();
    }
}
