<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('address')->nullable()->after('description');
            $table->string('email')->nullable()->after('address');
            $table->string('trade', 100)->nullable()->after('email');
            $table->string('ninea', 50)->nullable()->after('trade');
            $table->string('rccm', 50)->nullable()->after('ninea');

            // Charte graphique : interface mobile et PDF des devis
            $table->string('primary_color', 7)->default('#1D4ED8')->after('logo_url');
            $table->string('secondary_color', 7)->default('#0F172A')->after('primary_color');
            $table->string('accent_color', 7)->default('#F59E0B')->after('secondary_color');

            // Valeurs proposées à la création d'un devis
            $table->string('default_deposit_type', 10)->default('none')->after('accent_color');
            $table->unsignedBigInteger('default_deposit_value')->default(0)->after('default_deposit_type');
            $table->unsignedSmallInteger('quote_validity_days')->default(30)->after('default_deposit_value');
            $table->text('quote_footer')->nullable()->after('quote_validity_days');

            $table->dropColumn('color_code');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('color_code')->nullable();
            $table->dropColumn([
                'address', 'email', 'trade', 'ninea', 'rccm',
                'primary_color', 'secondary_color', 'accent_color',
                'default_deposit_type', 'default_deposit_value', 'quote_validity_days', 'quote_footer',
            ]);
        });
    }
};
