<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Client minimal de WAHA (WhatsApp HTTP API) : envoi d'un texte ou d'un fichier.
 * Configuration : `services.waha` (WAHA_BASE_URL, WAHA_API_KEY, WAHA_SESSION).
 */
class WahaService
{
    public function isConfigured(): bool
    {
        return config('services.waha.base_url') !== '' && config('services.waha.api_key') !== '';
    }

    /**
     * Envoie un message texte.
     *
     * @throws RuntimeException|RequestException
     */
    public function sendText(string $chatId, string $text): array
    {
        return $this->client()
            ->post('/api/sendText', [
                'session' => config('services.waha.session'),
                'chatId' => $chatId,
                'text' => $text,
            ])
            ->throw()
            ->json() ?? [];
    }

    /**
     * Envoie un fichier (contenu binaire, transmis en base64) avec une légende.
     *
     * @throws RuntimeException|RequestException
     */
    public function sendFile(string $chatId, string $content, string $filename, string $mimetype, ?string $caption = null): array
    {
        return $this->client()
            ->post('/api/sendFile', [
                'session' => config('services.waha.session'),
                'chatId' => $chatId,
                'file' => [
                    'mimetype' => $mimetype,
                    'filename' => $filename,
                    'data' => base64_encode($content),
                ],
                'caption' => $caption,
            ])
            ->throw()
            ->json() ?? [];
    }

    /**
     * Le numéro a-t-il un compte WhatsApp ? null si la vérification est impossible
     * (WAHA non configuré ou indisponible) : l'appelant décide alors de ne pas bloquer.
     */
    public function numberExists(string $chatId): ?bool
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $response = $this->client()
                ->get('/api/contacts/check-exists', [
                    'session' => config('services.waha.session'),
                    'phone' => strtok($chatId, '@'),
                ])
                ->throw()
                ->json();

            return (bool) ($response['numberExists'] ?? false);
        } catch (\Throwable $e) {
            Log::warning('Vérification WhatsApp impossible', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function client(): PendingRequest
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('WAHA n\'est pas configuré (WAHA_BASE_URL / WAHA_API_KEY).');
        }

        return Http::baseUrl(config('services.waha.base_url'))
            ->withHeaders(['X-Api-Key' => config('services.waha.api_key')])
            ->acceptJson()
            ->timeout(config('services.waha.timeout'));
    }
}
