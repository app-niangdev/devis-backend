<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Interfaces\UserServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function __construct(
        private readonly UserServiceInterface $userService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('admin');

        $users = $this->userService->list(
            perPage:    (int) $request->input('per_page', 10),
            search:     trim($request->input('search', '')),
            searchRole: trim($request->input('searchRole', '')),
        );

        return ApiResponse::paginated($users, 'Liste des utilisateurs récupérée avec succès');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $this->authorizeRoles($request, ['ADMIN']);

        $user = $this->userService->find($id);

        return ApiResponse::success($user);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->create($request->validated(), $request->user());

        return ApiResponse::success($user, 'Utilisateur créé avec succès', 201);
    }

    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $user = $this->userService->update($id, $request->validated(), $request->user());

        return ApiResponse::success($user, 'Utilisateur modifié avec succès');
    }

    /**
     * Nouveau mot de passe provisoire : le gestionnaire refera le parcours de première connexion.
     */
    public function resetAccess(Request $request, string $id): JsonResponse
    {
        Gate::authorize('admin');

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ], [
            'password.required' => 'Le mot de passe provisoire est obligatoire.',
            'password.min'      => 'Le mot de passe provisoire doit contenir au moins 8 caractères.',
        ]);

        $user = $this->userService->resetAccess($id, $validated['password']);

        return ApiResponse::success($user, 'Accès réinitialisé : communiquez le mot de passe provisoire au gestionnaire.');
    }

    public function disable(Request $request, string $id): JsonResponse
    {
        $this->authorizeRoles($request, ['ADMIN']);

        $user = $this->userService->disable($id, $request->user());

        return ApiResponse::success($user, 'Utilisateur désactivé avec succès');
    }

    public function toggleStatus(Request $request, string $id): JsonResponse
    {
        $this->authorizeRoles($request, ['ADMIN']);

        $user = $this->userService->toggleStatus($id, $request->user());

        $message = $user->status
            ? 'Utilisateur activé avec succès.'
            : 'Utilisateur désactivé avec succès.';

        return ApiResponse::success($user, $message);
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $this->authorizeRoles($request, ['ADMIN']);

        $user = $this->userService->restore($id, $request->user());

        return ApiResponse::success($user, 'Utilisateur réactivé avec succès.');
    }

    public function destroy(string $id): JsonResponse
    {
        Gate::authorize('admin');

        $this->userService->forceDelete($id);

        return ApiResponse::success(null, 'Utilisateur supprimé définitivement.');
    }

    private function authorizeRoles(Request $request, array $roles): void
    {
        if (!in_array($request->user()?->role?->name, $roles)) {
            abort(403, 'Accès non autorisé.');
        }
    }
}
