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
            $table->string('code')->unique(); // "Prod-001"
            
            // Allgemeine Infos
            $table->json('name');            // {"de": "Semmel", "en": "Bread Roll"}
            $table->json('category');        // {"de": "Backwaren", "en": "Baked Goods"}
            $table->json('super_category')->nullable();
            
            $table->string('image_path')->nullable(); // "Prod-001.jpg"
            $table->boolean('is_active')->default(true);

            // --- HIER IST DIE WICHTIGE NEUE SPALTE ---
            $table->integer('sort_order')->default(0);
            
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
