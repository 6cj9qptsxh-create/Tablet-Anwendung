<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_nodes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('image_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('tour_segments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('from_node_id')->constrained('tour_nodes')->cascadeOnDelete();
            $table->foreignId('to_node_id')->constrained('tour_nodes')->cascadeOnDelete();
            $table->string('mode', 32)->default('hike'); // hike, bike, ebike, sled, ski
            $table->boolean('bidirectional')->default(true);
            $table->decimal('distance_km', 8, 2)->default(0);
            $table->unsignedInteger('elevation_m')->default(0);
            $table->unsignedInteger('duration_min')->nullable();
            $table->json('geojson')->nullable();
            $table->string('gpx_path')->nullable();
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->json('description')->nullable();
            $table->string('category', 64)->default('hiking');
            $table->string('season', 32)->nullable();
            // Fallback-Stats wenn keine Variante / keine Segmente (migrierte Alt-Touren)
            $table->decimal('dist', 8, 2)->default(0);
            $table->unsignedInteger('alt')->default(0);
            $table->unsignedTinyInteger('difficulty')->default(1);
            $table->unsignedTinyInteger('fitness')->default(1);
            $table->json('tags')->nullable();
            $table->json('images')->nullable();
            $table->string('video')->nullable();
            $table->string('komoot_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_tour_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('trip_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->decimal('distance_km', 8, 2)->default(0);
            $table->unsignedInteger('elevation_m')->default(0);
            $table->unsignedInteger('duration_min')->nullable();
            $table->timestamps();
        });

        Schema::create('trip_variant_segment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_variant_id')->constrained('trip_variants')->cascadeOnDelete();
            $table->foreignId('tour_segment_id')->constrained('tour_segments')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('reversed')->default(false);
            $table->unique(['trip_variant_id', 'tour_segment_id', 'position'], 'tvs_variant_segment_pos');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_variant_segment');
        Schema::dropIfExists('trip_variants');
        Schema::dropIfExists('trips');
        Schema::dropIfExists('tour_segments');
        Schema::dropIfExists('tour_nodes');
    }
};
