<?php

namespace App\Http\Requests\Concerns;

use App\Models\Quote;
use Illuminate\Validation\Rule;

/**
 * Règles communes aux informations d'une entreprise, modifiables par l'administrateur
 * et par le gestionnaire (charte graphique, mentions légales, réglages des devis).
 */
trait TenantProfileRules
{
    protected function tenantProfileRules(): array
    {
        $color = ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];

        return [
            'slogan'                => ['nullable', 'string', 'max:255'],
            'description'           => ['nullable', 'string'],
            'address'               => ['nullable', 'string', 'max:255'],
            'email'                 => ['nullable', 'email', 'max:255'],
            'trade'                 => ['nullable', 'string', 'max:100'],
            'ninea'                 => ['nullable', 'string', 'max:50'],
            'rccm'                  => ['nullable', 'string', 'max:50'],
            'phone_other'           => ['nullable', 'string', 'max:20'],
            'phone_call'            => ['nullable', 'string', 'max:20'],
            'phone_whatsapp'        => ['nullable', 'string', 'max:20'],
            'logo'                  => ['nullable', 'image', 'max:2048'],
            'remove_logo'           => ['nullable', 'boolean'],
            'snap'                  => ['nullable', 'string', 'max:255'],
            'instagram'             => ['nullable', 'string', 'max:255'],
            'facebook'              => ['nullable', 'string', 'max:255'],
            'tiktok'                => ['nullable', 'string', 'max:255'],
            'short_name'            => ['nullable', 'string', 'max:255'],
            'primary_color'         => $color,
            'secondary_color'       => $color,
            'accent_color'          => $color,
            'default_deposit_type'  => ['nullable', Rule::in(Quote::DEPOSIT_TYPES)],
            'default_deposit_value' => [
                'nullable', 'integer', 'min:0',
                Rule::when($this->input('default_deposit_type') === Quote::DEPOSIT_PERCENT, ['min:1', 'max:100']),
            ],
            'quote_validity_days'   => ['nullable', 'integer', 'min:1', 'max:365'],
            'quote_footer'          => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function tenantProfileMessages(): array
    {
        return [
            'email.email'                 => "L'adresse e-mail n'est pas valide.",
            'logo.image'                  => 'Le logo doit être une image.',
            'logo.max'                    => 'Le logo ne doit pas dépasser 2 Mo.',
            'primary_color.regex'         => 'La couleur principale doit être au format #RRGGBB.',
            'secondary_color.regex'       => 'La couleur secondaire doit être au format #RRGGBB.',
            'accent_color.regex'          => "La couleur d'accent doit être au format #RRGGBB.",
            'default_deposit_type.in'     => "Le type d'acompte est invalide.",
            'default_deposit_value.min'   => "La valeur de l'acompte est invalide.",
            'default_deposit_value.max'   => "Le pourcentage d'acompte ne peut pas dépasser 100 %.",
            'quote_validity_days.min'     => 'La durée de validité doit être d\'au moins 1 jour.',
            'quote_validity_days.max'     => 'La durée de validité ne peut pas dépasser 365 jours.',
        ];
    }

    /** Couleurs saisies en minuscules ou majuscules : stockées en majuscules. */
    protected function normalizeColors(): void
    {
        foreach (['primary_color', 'secondary_color', 'accent_color'] as $field) {
            if ($this->filled($field)) {
                $this->merge([$field => strtoupper((string) $this->input($field))]);
            }
        }
    }
}
