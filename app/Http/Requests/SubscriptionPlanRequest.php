<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création et modification d'un forfait. En modification, le prix et la durée sont refusés :
 * un nouveau tarif passe par un nouveau forfait.
 */
class SubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (!$this->isUpdate()) {
            $this->merge([
                'currency' => strtoupper((string) ($this->input('currency') ?: config('subscriptions.default_currency', 'XOF'))),
            ]);
        }
    }

    public function rules(): array
    {
        $rules = [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('subscription_plans', 'name')->whereNull('deleted_at')->ignore($this->route('id')),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'position'    => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];

        if ($this->isUpdate()) {
            return $rules + [
                'duration_months' => ['prohibited'],
                'price'           => ['prohibited'],
                'currency'        => ['prohibited'],
            ];
        }

        return $rules + [
            'duration_months' => ['required', 'integer', 'min:1', 'max:60'],
            'price'           => ['required', 'numeric', 'min:0'],
            'currency'        => ['required', 'string', 'size:3'],
        ];
    }

    public function messages(): array
    {
        $locked = 'Le prix et la durée d\'un forfait ne sont pas modifiables. Désactivez ce forfait et créez-en un nouveau.';

        return [
            'name.required'            => 'Le nom du forfait est obligatoire.',
            'name.unique'              => 'Un forfait porte déjà ce nom.',
            'duration_months.required' => 'La durée est obligatoire.',
            'duration_months.min'      => 'La durée doit être d\'au moins 1 mois.',
            'duration_months.max'      => 'La durée ne peut pas dépasser 60 mois.',
            'price.required'           => 'Le prix est obligatoire.',
            'price.min'                => 'Le prix ne peut pas être négatif.',
            'duration_months.prohibited' => $locked,
            'price.prohibited'         => $locked,
            'currency.prohibited'      => $locked,
        ];
    }

    private function isUpdate(): bool
    {
        return $this->route('id') !== null;
    }
}
