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
        Schema::create('checkpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained(); // L'ouvrier OU la machine (IoT) qui a scanné
            
            // Géolocalisation
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('location_name')->nullable(); // Ex: "Entrepôt Paris Sud"

            // Données flexibles (Température, Humidité, Vitesse du camion, etc.)
            $table->json('metadata')->nullable(); 

            // Sécurité
            $table->string('hash')->nullable(); // Hash du scan précédent pour l'intégrité
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkpoints');
    }
};
