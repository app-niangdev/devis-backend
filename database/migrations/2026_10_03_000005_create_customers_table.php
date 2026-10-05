<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            // 9 chiffres, sans indicatif (ex. 771234567)
            $table->string('phone', 9);
            $table->string('address')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'name']);
        });

        DB::statement('CREATE UNIQUE INDEX customers_tenant_phone_unique ON customers (tenant_id, phone) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
