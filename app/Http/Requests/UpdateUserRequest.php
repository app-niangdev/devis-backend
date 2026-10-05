<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Support\SenegalPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modification d'un compte par l'administrateur. Changer le numéro de connexion révoque
 * les sessions du gestionnaire et lui fera confirmer le nouveau numéro par code WhatsApp.
 * Le mot de passe se change via « réinitialiser l'accès ».
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['phone_one', 'phone_two'] as $field) {
            if ($this->filled($field)) {
                $this->merge([$field => SenegalPhone::clean($this->input($field))]);
            }
        }
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name'  => ['sometimes', 'required', 'string', 'max:255'],
            'username'   => ['nullable', 'string', 'max:255'],
            'email'      => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'phone_one'  => [
                'sometimes', 'required', 'string', 'regex:' . SenegalPhone::PATTERN,
                Rule::unique('users', 'phone_one')->whereNull('deleted_at')->ignore($id),
            ],
            'phone_two'  => ['nullable', 'string', 'regex:' . SenegalPhone::PATTERN],
            'address'    => ['nullable', 'string', 'max:255'],
            'role_id'    => ['sometimes', 'required', 'exists:roles,id'],
            'status'     => ['nullable', 'boolean'],
            'tenant_id'  => [
                Rule::requiredIf(function () {
                    if (!$this->filled('role_id')) {
                        return false;
                    }

                    return Role::find($this->input('role_id'))?->name !== 'ADMIN';
                }),
                'nullable',
                'exists:tenants,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'Le prénom est obligatoire.',
            'last_name.required'  => 'Le nom est obligatoire.',
            'email.email'         => "L'adresse email n'est pas valide.",
            'email.unique'        => 'Cette adresse e-mail est déjà utilisée.',
            'phone_one.required'  => 'Le numéro de téléphone est obligatoire.',
            'phone_one.regex'     => 'Le numéro doit comporter 9 chiffres et commencer par 70, 71, 75, 76, 77 ou 78.',
            'phone_one.unique'    => 'Ce numéro est déjà utilisé par un autre compte.',
            'phone_two.regex'     => 'Le numéro secondaire doit comporter 9 chiffres et commencer par 70, 71, 75, 76, 77 ou 78.',
            'role_id.exists'      => 'Le rôle sélectionné est invalide.',
            'status.boolean'      => 'Le statut doit être vrai ou faux.',
            'tenant_id.required'  => "L'entreprise est obligatoire pour un gestionnaire.",
            'tenant_id.exists'    => "L'entreprise sélectionnée est invalide.",
        ];
    }
}
