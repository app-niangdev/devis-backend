<?php

namespace App\Services;

use App\Interfaces\TenantRepositoryInterface;
use App\Interfaces\TenantServiceInterface;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TenantService implements TenantServiceInterface
{
    private const LOGO_DISK = 'public';
    private const LOGO_DIRECTORY = 'tenants/logos';

    /** Colonnes non nulles : une valeur vide garde la valeur actuelle (ou celle par défaut). */
    private const NOT_NULLABLE = [
        'primary_color', 'secondary_color', 'accent_color',
        'default_deposit_type', 'default_deposit_value', 'quote_validity_days', 'state',
    ];

    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function list(int $perPage, string $search, ?string $approvalStatus = null): LengthAwarePaginator
    {
        return $this->tenantRepository->paginate($perPage, $search, $approvalStatus);
    }

    public function find(int|string $id): Tenant
    {
        return $this->tenantRepository->findById($id);
    }

    public function create(array $data): Tenant
    {
        $data = $this->withoutEmptyDefaults($data);
        $userId = $data['user_id'] ?? null;
        unset($data['user_id']);

        $logo = $data['logo'] ?? null;
        unset($data['logo']);

        $manager = $userId ? $this->resolveManager($userId) : null;

        if ($logo instanceof UploadedFile) {
            $data['logo_url'] = $this->storeLogo($logo);
        }

        $tenant = DB::transaction(function () use ($data, $manager) {
            $tenant = $this->tenantRepository->create($data);

            if ($manager) {
                $manager->update(['tenant_id' => $tenant->id]);
            }

            // Période d'essai offerte à la création
            $this->subscriptions->createTrial($tenant, auth()->id());

            return $tenant;
        });

        return $tenant->fresh();
    }

    public function update(int|string $id, array $data): Tenant
    {
        $tenant = $this->tenantRepository->findById($id);
        $data = $this->withoutEmptyDefaults($data);

        $userId = array_key_exists('user_id', $data) ? $data['user_id'] : false;
        unset($data['user_id']);

        $logo = $data['logo'] ?? null;
        $removeLogo = (bool) ($data['remove_logo'] ?? false);
        unset($data['logo'], $data['remove_logo']);

        if ($logo instanceof UploadedFile) {
            $this->deleteLogo($tenant->logo_url);
            $data['logo_url'] = $this->storeLogo($logo);
        } elseif ($removeLogo) {
            $this->deleteLogo($tenant->logo_url);
            $data['logo_url'] = null;
        }

        $tenant = $this->tenantRepository->update($tenant, $data);

        if ($userId !== false) {
            $manager = $userId ? $this->resolveManager($userId, currentTenantId: $tenant->id) : null;

            User::where('tenant_id', $tenant->id)
                ->when($manager, fn ($query) => $query->whereKeyNot($manager->id))
                ->update(['tenant_id' => null]);

            if ($manager && $manager->tenant_id !== $tenant->id) {
                $manager->update(['tenant_id' => $tenant->id]);
            }
        }

        return $tenant;
    }

    /**
     * Informations modifiées par le gestionnaire de l'entreprise (logo compris).
     */
    public function updateProfile(Tenant $tenant, array $data): Tenant
    {
        $data = $this->withoutEmptyDefaults($data);
        $logo = $data['logo'] ?? null;
        $removeLogo = (bool) ($data['remove_logo'] ?? false);
        unset($data['logo'], $data['remove_logo']);

        if ($logo instanceof UploadedFile) {
            $this->deleteLogo($tenant->logo_url);
            $data['logo_url'] = $this->storeLogo($logo);
        } elseif ($removeLogo) {
            $this->deleteLogo($tenant->logo_url);
            $data['logo_url'] = null;
        }

        return $this->tenantRepository->update($tenant, $data);
    }

    public function disable(int|string $id): Tenant
    {
        $tenant = $this->tenantRepository->findById($id);

        $this->tenantRepository->delete($tenant);

        return $tenant;
    }

    public function toggleStatus(int|string $id): Tenant
    {
        $tenant = $this->tenantRepository->findById($id);

        return $this->tenantRepository->update($tenant, [
            'state' => !$tenant->state,
        ]);
    }

    public function restore(int|string $id): Tenant
    {
        $tenant = $this->tenantRepository->findTrashedById($id);

        $this->tenantRepository->restore($tenant);

        return $tenant->fresh();
    }

    public function forceDelete(int|string $id): void
    {
        $tenant = $this->tenantRepository->findById($id);
        $this->deleteLogo($tenant->logo_url);

        User::where('tenant_id', $id)->update(['tenant_id' => null]);

        $this->tenantRepository->forceDelete($id);
    }

    private function withoutEmptyDefaults(array $data): array
    {
        foreach (self::NOT_NULLABLE as $field) {
            if (array_key_exists($field, $data) && $data[$field] === null) {
                unset($data[$field]);
            }
        }

        // Sans acompte, la valeur n'a pas de sens
        if (($data['default_deposit_type'] ?? null) === 'none') {
            $data['default_deposit_value'] = 0;
        }

        return $data;
    }

    private function resolveManager(int|string $userId, ?int $currentTenantId = null): User
    {
        $manager = User::find($userId);

        if (! $manager) {
            abort(404, 'Utilisateur introuvable.');
        }

        if ($manager->role?->name !== 'MANAGER') {
            abort(422, 'Seul un utilisateur avec le rôle MANAGER peut être affecté à un tenant.');
        }

        if ($manager->tenant_id && $manager->tenant_id !== $currentTenantId) {
            abort(422, 'Cet utilisateur est déjà affecté à un tenant.');
        }

        return $manager;
    }

    private function storeLogo(UploadedFile $logo): string
    {
        $path = $logo->store(self::LOGO_DIRECTORY, self::LOGO_DISK);

        return Storage::disk(self::LOGO_DISK)->url($path);
    }

    private function deleteLogo(?string $logoUrl): void
    {
        if (! $logoUrl) {
            return;
        }

        $path = self::LOGO_DIRECTORY . '/' . basename(parse_url($logoUrl, PHP_URL_PATH) ?? '');

        if (Storage::disk(self::LOGO_DISK)->exists($path)) {
            Storage::disk(self::LOGO_DISK)->delete($path);
        }
    }
}
