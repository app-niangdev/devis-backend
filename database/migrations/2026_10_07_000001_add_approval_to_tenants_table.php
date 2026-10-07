<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // approved | pending (inscription depuis l'application, en attente de l'administrateur) | rejected.
            // Les entreprises créées par l'administrateur sont validées d'office.
            $table->string('approval_status', 10)->default('approved')->after('state');
            $table->timestamp('approval_reviewed_at')->nullable()->after('approval_status');
            $table->foreignId('approval_reviewed_by')->nullable()->after('approval_reviewed_at')->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 255)->nullable()->after('approval_reviewed_by');
            $table->index('approval_status');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['approval_status']);
            $table->dropConstrainedForeignId('approval_reviewed_by');
            $table->dropColumn(['approval_status', 'approval_reviewed_at', 'rejection_reason']);
        });
    }
};
