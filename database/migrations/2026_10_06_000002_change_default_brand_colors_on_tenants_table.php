<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Charte SN Devis : nouvelles couleurs par défaut des entreprises.
 * Ne modifie que la valeur DEFAULT des colonnes : les entreprises existantes
 * gardent leurs couleurs (cf. database/proposals pour leur mise à jour).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('primary_color', 7)->default('#00853F')->change();
            $table->string('secondary_color', 7)->default('#17202A')->change();
            $table->string('accent_color', 7)->default('#FDEF42')->change();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('primary_color', 7)->default('#1D4ED8')->change();
            $table->string('secondary_color', 7)->default('#0F172A')->change();
            $table->string('accent_color', 7)->default('#F59E0B')->change();
        });
    }
};
