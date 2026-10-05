<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('admin');

        $roles = Role::orderBy('label')->get();

        return ApiResponse::success($roles, 'Liste des rôles récupérée avec succès');
    }
}
