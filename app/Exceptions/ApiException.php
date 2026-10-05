<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Erreur métier renvoyée telle quelle au client, avec un code stable (error_code)
 * que les applications Flutter et Angular peuvent traiter.
 */
class ApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = 422,
        public readonly mixed $data = null,
    ) {
        parent::__construct($message);
    }

    /** Les détails (état d'abonnement, essais restants…) vont dans `payload`, pas dans `errors`. */
    public function render(): JsonResponse
    {
        return response()->json([
            'status' => $this->status,
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
            'payload' => $this->data,
        ], $this->status);
    }
}
