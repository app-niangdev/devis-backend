<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code_website')->unique();
            $table->string('slogan')->nullable();
            $table->text('description')->nullable();
            $table->string('phone_other')->nullable();
            $table->string('phone_call')->nullable();
            $table->string('phone_whatsapp')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('snap')->nullable();
            $table->string('instagram')->nullable();
            $table->string('facebook')->nullable();
            $table->string('tiktok')->nullable();
            $table->string('color_code')->nullable();
            $table->string('short_name')->nullable();
            $table->boolean('state')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
