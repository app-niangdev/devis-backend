<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'Succès', int $code = 200): JsonResponse
    {
        $response = response()->json([
            'status' => $code,
            'message' => $message,
            'payload' => $data,
        ], $code);

        // S'assurer que le Content-Type est bien JSON
        $response->headers->set('Content-Type', 'application/json; charset=utf-8');

        return $response;
    }

    public static function error(string $message = 'Erreur', int $code = 500, mixed $data = null, ?string $errorCode = null): JsonResponse
    {
        $responseData = [
            'status' => $code,
            'message' => $message,
        ];

        // Code stable exploité par les applications (ex. SUBSCRIPTION_EXPIRED, OTP_INVALID)
        if ($errorCode) {
            $responseData['error_code'] = $errorCode;
        }

        // Si des erreurs de validation sont fournies (tableau associatif), les inclure dans 'errors'
        if (is_array($data) && !empty($data) && !isset($data[0])) {
            // C'est un tableau associatif (erreurs de validation Laravel)
            $responseData['errors'] = $data;
        } else {
            // Sinon, mettre dans payload
            $responseData['payload'] = $data;
        }

        $response = response()->json($responseData, $code);

        // S'assurer que le Content-Type est bien JSON
        $response->headers->set('Content-Type', 'application/json; charset=utf-8');

        return $response;
    }

    public static function paginated(\Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator, string $message = 'Succès', int $code = 200): JsonResponse
    {
        $response = response()->json([
            'status'  => $code,
            'message' => $message,
            'payload' => $paginator->items(),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ], $code);

        $response->headers->set('Content-Type', 'application/json; charset=utf-8');

        return $response;
    }
}
