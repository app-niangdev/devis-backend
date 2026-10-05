<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Tampon généré à partir des informations de l'entreprise, apposé sur les devis
            $table->boolean('stamp_enabled')->default(false)->after('quote_footer');
            $table->string('stamp_color', 7)->default('#1E3A8A')->after('stamp_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['stamp_enabled', 'stamp_color']);
        });
    }
};
