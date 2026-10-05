<?php

namespace App\Http\Requests;

use App\Models\Quote;
use App\Models\QuoteItem;
use App\Support\SenegalPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isManager();
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('customer.mode') === 'new' && $this->filled('customer.phone')) {
            $this->merge(['customer' => array_merge(
                (array) $this->input('customer'),
                ['phone' => SenegalPhone::clean($this->input('customer.phone'))],
            )]);
        }
    }

    public function rules(): array
    {
        return [
            'customer'              => ['required', 'array'],
            'customer.mode'         => ['required', 'in:existing,new'],
            'customer.id'           => ['required_if:customer.mode,existing', 'nullable', 'integer'],
            'customer.name'         => ['required_if:customer.mode,new', 'nullable', 'string', 'min:2', 'max:100'],
            'customer.phone'        => ['required_if:customer.mode,new', 'nullable', 'string', 'regex:' . SenegalPhone::PATTERN],
            'title'                 => ['nullable', 'string', 'max:255'],
            'items'                 => ['required', 'array', 'min:1', 'max:200'],
            'items.*.kind'          => ['required', Rule::in(QuoteItem::KINDS)],
            'items.*.product_id'    => ['nullable', 'integer'],
            'items.*.designation'   => ['nullable', 'string', 'max:255'],
            'items.*.unit_name'     => ['nullable', 'string', 'max:30'],
            'items.*.quantity'      => ['required', 'numeric', 'min:0.001', 'max:1000000'],
            'items.*.unit_price'    => ['required', 'integer', 'min:0', 'max:1000000000'],
            'discount'              => ['nullable', 'integer', 'min:0'],
            'deposit_type'          => ['nullable', Rule::in(Quote::DEPOSIT_TYPES)],
            'deposit_value'         => ['nullable', 'integer', 'min:0'],
            'valid_until'           => ['nullable', 'date'],
            'notes'                 => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer.required'            => 'Choisissez un client.',
            'customer.mode.required'       => 'Choisissez un client.',
            'customer.id.required_if'      => 'Choisissez un client.',
            'customer.name.required_if'    => 'Le nom du client est obligatoire.',
            'customer.name.min'            => 'Le nom du client doit contenir au moins 2 caractères.',
            'customer.phone.required_if'   => 'Le numéro du client est obligatoire.',
            'customer.phone.regex'         => 'Le numéro doit comporter 9 chiffres et commencer par 70, 71, 75, 76, 77 ou 78.',
            'items.required'               => 'Ajoutez au moins une ligne au devis.',
            'items.min'                    => 'Ajoutez au moins une ligne au devis.',
            'items.max'                    => 'Un devis ne peut pas dépasser 200 lignes.',
            'items.*.kind.required'        => 'Précisez s\'il s\'agit d\'une fourniture ou de main-d\'œuvre.',
            'items.*.kind.in'              => 'Le type de ligne est invalide.',
            'items.*.quantity.required'    => 'La quantité est obligatoire.',
            'items.*.quantity.min'         => 'La quantité doit être supérieure à 0.',
            'items.*.unit_price.required'  => 'Le prix unitaire est obligatoire.',
            'items.*.unit_price.integer'   => 'Le prix unitaire doit être un montant entier en FCFA.',
            'items.*.unit_price.min'       => 'Le prix unitaire ne peut pas être négatif.',
            'discount.integer'             => 'La remise doit être un montant entier en FCFA.',
            'deposit_type.in'              => 'Le type d\'acompte est invalide.',
            'deposit_value.integer'        => 'La valeur de l\'acompte doit être un nombre entier.',
            'valid_until.date'             => 'La date de validité est invalide.',
        ];
    }
}
