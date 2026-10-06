<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Types d'abonnement (forfaits). Le prix et la durée ne changent jamais : pour un nouveau
        // tarif, l'administrateur désactive l'ancien forfait et en crée un autre.
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedSmallInteger('duration_months');
            $table->decimal('price', 12, 2);
            $table->string('currency', 3)->default('XOF');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // Forfait choisi ; null pour l'essai et les abonnements saisis avant les forfaits.
        // Le nom et le montant restent recopiés dans l'abonnement (historique).
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('subscription_plan_id')->nullable()->after('tenant_id')
                ->constrained('subscription_plans')->nullOnDelete();
        });

        $currency = config('subscriptions.default_currency', 'XOF');
        DB::table('subscription_plans')->insert(collect([
            ['name' => 'Forfait 3 mois', 'duration_months' => 3, 'price' => 6000],
            ['name' => 'Forfait 6 mois', 'duration_months' => 6, 'price' => 12000],
            ['name' => 'Forfait 1 an', 'duration_months' => 12, 'price' => 25000],
        ])->map(fn (array $plan, int $index) => $plan + [
            'currency' => $currency,
            'is_active' => true,
            'position' => $index + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_plan_id');
        });

        Schema::dropIfExists('subscription_plans');
    }
};
