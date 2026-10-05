<?php

namespace App\Services;

use App\Models\Quote;
use App\Models\Tenant;
use App\Support\SenegalPhone;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * PDF d'un devis aux couleurs de l'entreprise, et envoi au client sur WhatsApp.
 */
class QuoteDocumentService
{
    public function __construct(
        private readonly WahaService $waha,
        private readonly StampService $stamps,
    ) {
    }

    public function filename(Quote $quote): string
    {
        return 'Devis-' . $quote->quote_number . '.pdf';
    }

    public function pdf(Quote $quote): string
    {
        $quote->loadMissing(['tenant', 'customer', 'user:id,first_name,last_name', 'items']);
        $tenant = $quote->tenant;

        return Pdf::loadView('pdf.quote', [
            'quote' => $quote,
            'tenant' => $tenant,
            'logo' => $this->logoDataUri($tenant),
            'stamp' => $tenant->stamp_enabled ? $this->stamps->dataUri($tenant) : null,
            'phones' => array_values(array_unique(array_filter(array_map(
                fn ($p) => SenegalPhone::format(SenegalPhone::normalize($p)) ?? $p,
                [$tenant->phone_call, $tenant->phone_whatsapp, $tenant->phone_other],
            )))),
            'author' => $quote->user ? trim($quote->user->first_name . ' ' . $quote->user->last_name) : null,
        ])
            ->setPaper('a4')
            // Seuls les caractères utilisés de la police sont intégrés : fichier bien plus léger
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    public function whatsappAvailable(Quote $quote): bool
    {
        $quote->loadMissing('customer');

        return $this->waha->isConfigured() && SenegalPhone::chatId($quote->customer?->phone) !== null;
    }

    /**
     * Envoi immédiat du devis ; lève une exception si WAHA refuse ou ne répond pas.
     *
     * @throws \Throwable
     */
    public function sendWhatsapp(Quote $quote): void
    {
        $quote->loadMissing(['tenant', 'customer']);
        $chatId = SenegalPhone::chatId($quote->customer?->phone)
            ?? throw new \RuntimeException('Le client n\'a pas de numéro WhatsApp valide.');

        $money = fn (int $v) => number_format($v, 0, ',', ' ') . ' FCFA';
        $name = $quote->customer?->name;
        $caption = 'Bonjour' . ($name ? ' ' . $name : '') . ",\n\n"
            . ($quote->status === Quote::ACCEPTED
                ? "Voici votre devis {$quote->quote_number} accepté de *{$quote->tenant->name}*"
                : "Voici votre devis {$quote->quote_number} de *{$quote->tenant->name}*")
            . " : *{$money($quote->total_amount)}*."
            . ($quote->deposit_amount > 0 ? "\nAcompte à la commande : {$money($quote->deposit_amount)}." : '')
            . ($quote->valid_until && $quote->isEditable() ? "\nOffre valable jusqu'au {$quote->valid_until->format('d/m/Y')}." : '')
            . "\n\nLe devis est joint à ce message.";

        $this->waha->sendFile($chatId, $this->pdf($quote), $this->filename($quote), 'application/pdf', $caption);
    }

    /** Logo intégré au PDF (dompdf ne charge pas d'URL distante). */
    private function logoDataUri(Tenant $tenant): ?string
    {
        if (!$tenant->logo_url) {
            return null;
        }

        $path = 'tenants/logos/' . basename((string) parse_url($tenant->logo_url, PHP_URL_PATH));
        $disk = Storage::disk('public');

        if (!$disk->exists($path)) {
            return null;
        }

        return 'data:' . ($disk->mimeType($path) ?: 'image/png') . ';base64,' . base64_encode($disk->get($path));
    }
}
