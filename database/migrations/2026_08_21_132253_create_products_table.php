<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('available')->default(true);
            $table->decimal('unit_price', 12, 2);
            $table->string('image_url')->nullable();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('category_id')->nullable()->constrained('categories');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
