<?php

namespace App\Services;

use App\Interfaces\UserRepositoryInterface;
use App\Interfaces\UserServiceInterface;
use App\Models\Role;
use App\Models\User;
use App\Support\SenegalPhone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class UserService implements UserServiceInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly WahaService $waha,
    ) {}

    public function list(int $perPage, string $search, string $searchRole): LengthAwarePaginator
    {
        return $this->userRepository->paginate($perPage, $search, $searchRole);
    }

    public function find(int|string $id): User
    {
        return $this->userRepository->findById($id);
    }

    /**
     * Le mot de passe saisi par l'administrateur est provisoire : changé à la première connexion.
     */
    public function create(array $data, User $authUser): User
    {
        if ($this->isManagerRole($data['role_id'] ?? null)) {
            $this->ensureWhatsapp($data['phone_one']);
        }

        $data['must_change_password'] = true;
        $data['phone_verified_at']    = null;

        return $this->userRepository->create($data)->load('role');
    }

    public function update(int|string $id, array $data, User $authUser): User
    {
        $user = $this->userRepository->findById($id);
        unset($data['password']);

        $phoneChanged = isset($data['phone_one']) && $data['phone_one'] !== $user->phone_one;

        if ($phoneChanged && $user->isManager()) {
            $this->ensureWhatsapp($data['phone_one']);
        }

        if ($phoneChanged) {
            // Le nouveau numéro devra être confirmé par code WhatsApp ; les sessions sont fermées
            $data['phone_verified_at'] = null;
            $data['token_version']     = $user->token_version + 1;
        }

        if (array_key_exists('status', $data) && $data['status'] === false && $user->status) {
            $data['token_version'] = ($data['token_version'] ?? $user->token_version) + 1;
        }

        return $this->userRepository->update($user, $data);
    }

    /**
     * Nouveau mot de passe provisoire : relance le parcours de première connexion
     * (code WhatsApp puis choix du mot de passe) et ferme toutes les sessions.
     */
    public function resetAccess(int|string $id, string $temporaryPassword): User
    {
        $user = $this->userRepository->findById($id);

        $user->forceFill([
            'password'             => $temporaryPassword,
            'must_change_password' => true,
            'token_version'        => $user->token_version + 1,
        ])->save();

        return $user->fresh('role');
    }

    public function disable(int|string $id, User $authUser): User
    {
        $user = $this->userRepository->findById($id);

        if ($user->id === $authUser->id) {
            abort(422, 'Vous ne pouvez pas vous désactiver vous-même.');
        }

        $user->revokeTokens();
        $this->userRepository->delete($user);

        return $user;
    }

    public function toggleStatus(int|string $id, User $authUser): User
    {
        $user = $this->userRepository->findById($id);

        if ($user->id === $authUser->id) {
            abort(422, 'Vous ne pouvez pas modifier votre propre statut.');
        }

        return $this->userRepository->update($user, [
            'status'        => !$user->status,
            'token_version' => $user->token_version + 1,
        ]);
    }

    public function restore(int|string $id, User $authUser): User
    {
        $user = $this->userRepository->findTrashedById($id);

        // Le numéro a pu être réattribué entre-temps : il sert d'identifiant de connexion
        if (User::where('phone_one', $user->phone_one)->exists()) {
            abort(422, 'Ce numéro est déjà utilisé par un autre compte : modifiez-le avant de réactiver celui-ci.');
        }

        $this->userRepository->restore($user);

        return $user->fresh('role');
    }

    public function forceDelete(int|string $id): void
    {
        $this->userRepository->forceDelete($id);
    }

    private function isManagerRole(mixed $roleId): bool
    {
        return $roleId && Role::find($roleId)?->name === 'MANAGER';
    }

    /**
     * Les codes de connexion partent sur WhatsApp : le numéro doit y avoir un compte.
     * Si WAHA ne peut pas répondre, on ne bloque pas la saisie.
     */
    private function ensureWhatsapp(string $phone): void
    {
        if ($this->waha->numberExists((string) SenegalPhone::chatId($phone)) === false) {
            throw ValidationException::withMessages([
                'phone_one' => "Ce numéro n'a pas de compte WhatsApp : le gestionnaire ne pourrait pas recevoir ses codes de connexion.",
            ]);
        }
    }
}
