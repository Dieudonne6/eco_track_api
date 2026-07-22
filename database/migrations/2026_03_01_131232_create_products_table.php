<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // Identifiant public (QR Code)
            $table->foreignId('company_id')->constrained(); // Créateur du produit
            $table->string('name');
            $table->string('sku')->unique(); // Référence interne
            $table->enum('status', ['created', 'in_transit', 'processed', 'delivered'])->default('created');
            $table->boolean('is_compromised')->default(false); // Flag si un scan manque ou est incohérent
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
