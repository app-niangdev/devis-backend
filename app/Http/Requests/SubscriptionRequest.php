<?php

namespace App\Http\Requests;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Abonnement d'une entreprise : l'administrateur choisit un forfait et une date de début ;
 * la date de fin, le nom et le montant viennent du forfait (SubscriptionService::attributesFor).
 * Les abonnements sans forfait (essai, anciennes saisies) gardent des dates saisies à la main.
 */
class SubscriptionRequest extends FormRequest
{
    private ?Subscription $existing = null;

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $withoutPlan = $this->existing() !== null && $this->existing()->subscription_plan_id === null;

        return [
            'tenant_id'            => ['required', Rule::exists('tenants', 'id')->whereNull('deleted_at')],
            'subscription_plan_id' => [$withoutPlan ? 'nullable' : 'required', Rule::exists('subscription_plans', 'id')->whereNull('deleted_at')],
            'starts_at'            => ['required', 'date'],
            'ends_at'              => ['exclude_with:subscription_plan_id', 'required', 'date', 'after_or_equal:starts_at'],
            'notes'                => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $planId = $this->input('subscription_plan_id');
            if (!$planId || $validator->errors()->has('subscription_plan_id')) {
                return;
            }

            // Un forfait désactivé n'est plus proposé, sauf pour l'abonnement qui l'utilise déjà
            $plan = SubscriptionPlan::find($planId);
            if ($plan && !$plan->is_active && (int) $planId !== $this->existing()?->subscription_plan_id) {
                $validator->errors()->add('subscription_plan_id', 'Ce forfait est désactivé : choisissez un forfait proposé.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'tenant_id.required'            => "L'entreprise est obligatoire.",
            'tenant_id.exists'              => "L'entreprise sélectionnée est invalide.",
            'subscription_plan_id.required' => 'Le forfait est obligatoire.',
            'subscription_plan_id.exists'   => 'Le forfait sélectionné est invalide.',
            'starts_at.required'            => 'La date de début est obligatoire.',
            'ends_at.required'              => 'La date de fin est obligatoire.',
            'ends_at.after_or_equal'        => 'La date de fin doit être postérieure ou égale à la date de début.',
        ];
    }

    /** Abonnement modifié (null en création). */
    public function existing(): ?Subscription
    {
        if ($this->existing === null && $id = $this->route('id')) {
            $this->existing = Subscription::find($id);
        }

        return $this->existing;
    }
}
