<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('user_id')->constrained('users');
            // Devis d'origine quand celui-ci est une copie
            $table->foreignId('source_quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->string('quote_number', 30);
            $table->string('status', 20)->default('draft');
            $table->string('title')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();

            // Montants en FCFA entiers
            $table->bigInteger('supplies_amount')->default(0);
            $table->bigInteger('labor_amount')->default(0);
            $table->bigInteger('gross_amount')->default(0);
            $table->bigInteger('discount')->default(0);
            $table->bigInteger('total_amount')->default(0);

            // Acompte demandé : none | percent (deposit_value = taux) | amount (deposit_value = montant)
            $table->string('deposit_type', 10)->default('none');
            $table->unsignedBigInteger('deposit_value')->default(0);
            $table->bigInteger('deposit_amount')->default(0);

            // Acompte encaissé (devis accepté)
            $table->bigInteger('deposit_received_amount')->nullable();
            $table->date('deposit_received_at')->nullable();
            $table->string('deposit_payment_method', 20)->nullable();
            $table->string('deposit_reference', 100)->nullable();

            $table->timestamp('sent_at')->nullable();
            // Date d'acceptation ou de refus
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'quote_number']);
            $table->index(['tenant_id', 'status', 'created_at']);
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();
            // Produit du catalogue d'où vient la ligne (simple référence) ; null pour une ligne libre
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            // supply (fourniture) | labor (main-d'œuvre)
            $table->string('kind', 10)->default('supply');
            $table->string('designation', 255);
            $table->string('unit_name', 30)->nullable();
            $table->decimal('quantity', 14, 3);
            $table->bigInteger('unit_price');
            $table->bigInteger('subtotal');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
    }
};
