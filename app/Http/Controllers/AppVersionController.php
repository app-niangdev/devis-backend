<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Version attendue de l'application mobile : l'app propose ou impose la mise à jour.
 */
class AppVersionController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success([
            'latest_version' => config('mobile.latest_version'),
            'min_version' => config('mobile.min_version'),
            'android_url' => config('mobile.android_url'),
            'release_notes' => config('mobile.release_notes'),
        ]);
    }
}
