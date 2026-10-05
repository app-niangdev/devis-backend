<?php

namespace App\Http\Controllers\Manager;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCompanyRequest;
use App\Interfaces\TenantServiceInterface;
use App\Models\Tenant;
use App\Services\StampService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Entreprise du gestionnaire : informations, charte graphique et réglages des devis.
 */
class CompanyController extends Controller
{
    public function __construct(
        private readonly TenantServiceInterface $tenants,
        private readonly SubscriptionService $subscriptions,
        private readonly StampService $stamps,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success($this->present($request->user()->tenant));
    }

    /** POST (et non PUT) : formulaire multipart avec logo. */
    public function update(UpdateCompanyRequest $request): JsonResponse
    {
        $tenant = $this->tenants->updateProfile($request->user()->tenant, $request->validated());

        return ApiResponse::success($this->present($tenant), 'Informations de l\'entreprise mises à jour.');
    }

    /** Aperçu du tampon généré à partir des informations de l'entreprise (couleur au choix). */
    public function stamp(Request $request): JsonResponse
    {
        $data = $request->validate(['color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/']], [
            'color.regex' => 'La couleur du tampon doit être au format #RRGGBB.',
        ]);
        $tenant = $request->user()->tenant;

        return ApiResponse::success($this->presentStamp($tenant, $data['color'] ?? null));
    }

    /** Active ou retire le tampon des devis, et enregistre sa couleur. */
    public function updateStamp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'color'   => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [
            'color.regex' => 'La couleur du tampon doit être au format #RRGGBB.',
        ]);

        $tenant = $request->user()->tenant;
        $tenant->update(array_filter([
            'stamp_enabled' => (bool) $data['enabled'],
            'stamp_color' => isset($data['color']) ? strtoupper($data['color']) : null,
        ], fn ($v) => $v !== null));

        return ApiResponse::success(
            $this->presentStamp($tenant),
            $tenant->stamp_enabled ? 'Le tampon figurera sur vos devis.' : 'Le tampon ne figurera plus sur vos devis.',
        );
    }

    public function subscription(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        return ApiResponse::success([
            'status' => $this->subscriptions->statusForTenant($tenant),
            'warning_days' => $this->subscriptions->warningDays(),
        ]);
    }

    private function present(Tenant $tenant): array
    {
        return $tenant->only([
            'id', 'name', 'short_name', 'code_website', 'slogan', 'description', 'address', 'email', 'trade', 'ninea', 'rccm',
            'phone_call', 'phone_whatsapp', 'phone_other', 'logo_url', 'snap', 'instagram', 'facebook', 'tiktok',
            'primary_color', 'secondary_color', 'accent_color',
            'default_deposit_type', 'default_deposit_value', 'quote_validity_days', 'quote_footer',
            'stamp_enabled', 'stamp_color',
        ]);
    }

    private function presentStamp(Tenant $tenant, ?string $color = null): array
    {
        $color = strtoupper($color ?: $tenant->stamp_color ?: StampService::DEFAULT_COLOR);

        return [
            'enabled' => (bool) $tenant->stamp_enabled,
            'color' => $color,
            'image' => base64_encode($this->stamps->png($tenant, $color)),
        ];
    }
}
