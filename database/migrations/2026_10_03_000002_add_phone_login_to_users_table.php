<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('users')
            ->whereNull('deleted_at')
            ->select('phone_one')
            ->groupBy('phone_one')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('phone_one');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Le téléphone devient l\'identifiant de connexion : corrigez d\'abord les numéros en double ('
                . $duplicates->implode(', ') . ').'
            );
        }

        Schema::table('users', function (Blueprint $table) {
            // Numéro confirmé par OTP WhatsApp ; remis à null quand l'administrateur le change
            $table->timestamp('phone_verified_at')->nullable()->after('phone_two');
            // Incrémenté pour révoquer tous les jetons de l'utilisateur
            $table->unsignedInteger('token_version')->default(0)->after('must_change_password');
        });

        // Le téléphone sert d'identifiant de connexion : unique parmi les comptes non supprimés
        DB::statement('CREATE UNIQUE INDEX users_phone_one_unique ON users (phone_one) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_phone_one_unique');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone_verified_at', 'token_version']);
        });
    }
};
