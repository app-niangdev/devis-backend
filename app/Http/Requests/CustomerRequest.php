<?php

namespace App\Http\Requests;

use App\Support\SenegalPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isManager();
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
            'name'    => ['required', 'string', 'min:2', 'max:100'],
            'phone'   => [
                'required', 'string', 'regex:' . SenegalPhone::PATTERN,
                Rule::unique('customers', 'phone')
                    ->where('tenant_id', $this->user()->tenant_id)
                    ->whereNull('deleted_at')
                    ->ignore($this->route('id')),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'email'   => ['nullable', 'email', 'max:255'],
            'notes'   => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => 'Le nom du client est obligatoire.',
            'name.min'       => 'Le nom du client doit contenir au moins 2 caractères.',
            'name.max'       => 'Le nom du client ne peut pas dépasser 100 caractères.',
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'phone.regex'    => 'Le numéro doit comporter 9 chiffres et commencer par 70, 71, 75, 76, 77 ou 78.',
            'phone.unique'   => 'Un client avec ce numéro existe déjà.',
            'email.email'    => "L'adresse e-mail n'est pas valide.",
        ];
    }
}
