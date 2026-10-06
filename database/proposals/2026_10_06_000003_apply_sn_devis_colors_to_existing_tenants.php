<?php

/*
 * PROPOSITION — NE PAS LANCER SANS VALIDATION.
 *
 * Ce fichier est volontairement hors de database/migrations : `php artisan migrate`
 * ne l'exécute pas. Pour l'appliquer après validation, le déplacer dans
 * database/migrations puis lancer `php artisan migrate`.
 *
 * Effet : bascule vers la charte SN Devis uniquement les entreprises qui ont
 * encore EXACTEMENT les trois anciennes couleurs par défaut (jamais personnalisées).
 * Une entreprise ayant changé ne serait-ce qu'une couleur n'est pas touchée.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD = ['primary_color' => '#1D4ED8', 'secondary_color' => '#0F172A', 'accent_color' => '#F59E0B'];
    private const NEW = ['primary_color' => '#00853F', 'secondary_color' => '#17202A', 'accent_color' => '#FDEF42'];

    public function up(): void
    {
        $this->swap(self::OLD, self::NEW);
    }

    public function down(): void
    {
        $this->swap(self::NEW, self::OLD);
    }

    private function swap(array $from, array $to): void
    {
        $query = DB::table('tenants');
        foreach ($from as $column => $color) {
            $query->whereRaw("UPPER({$column}) = ?", [$color]);
        }
        $query->update($to + ['updated_at' => now()]);
    }
};
