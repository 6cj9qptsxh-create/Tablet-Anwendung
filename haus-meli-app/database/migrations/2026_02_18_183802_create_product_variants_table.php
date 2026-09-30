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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            
            // Varianten-Infos
            $table->json('name')->nullable(); // "Klein", "Groß"
            
            // HIER SIND JETZT DIE TEXTE:
            $table->json('description')->nullable(); // "Weizengebäck mit Salz"
            $table->json('notes')->nullable();       // "Entnahme aus Schrank"
            
            $table->decimal('price', 10, 2);
            $table->string('size')->nullable();      // "500g"
            $table->integer('stock')->nullable();
            
            // Die Allergene als Liste ["A", "G"]
            $table->json('allergens')->nullable(); 
            
            // Deine Monats-Aktion (1, 2, 12...)
            $table->integer('sale_month')->nullable(); 
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
