<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_nodes', function (Blueprint $table) {
            $table->boolean('is_start_candidate')->default(false)->after('notes');
        });

        Schema::table('tour_segments', function (Blueprint $table) {
            $table->boolean('is_highlight')->default(false)->after('notes');
            $table->boolean('out_and_back')->default(false)->after('is_highlight');
        });

        Schema::create('tour_segment_modes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_segment_id')->constrained('tour_segments')->cascadeOnDelete();
            $table->string('mode', 32);
            $table->unsignedInteger('duration_min')->nullable();
            $table->unsignedTinyInteger('difficulty')->default(1);
            $table->unsignedTinyInteger('fitness')->default(1);
            $table->json('tags')->nullable();
            $table->json('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tour_segment_id', 'mode'], 'tour_segment_modes_segment_mode');
        });

        Schema::create('tour_segment_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_segment_id')->constrained('tour_segments')->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Bestehende Segmente → mindestens ein Modus-Profil aus Legacy-Spalten
        if (Schema::hasTable('tour_segments')) {
            $rows = DB::table('tour_segments')->select('id', 'mode', 'duration_min', 'tags')->get();
            $now = now();
            foreach ($rows as $row) {
                $mode = $row->mode ?: 'hike';
                // Nur hike/bike/ebike als Start; andere Modi trotzdem migrieren
                DB::table('tour_segment_modes')->insert([
                    'tour_segment_id' => $row->id,
                    'mode' => $mode,
                    'duration_min' => $row->duration_min,
                    'difficulty' => 1,
                    'fitness' => 1,
                    'tags' => $row->tags,
                    'description' => null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_segment_images');
        Schema::dropIfExists('tour_segment_modes');

        Schema::table('tour_segments', function (Blueprint $table) {
            $table->dropColumn(['is_highlight', 'out_and_back']);
        });

        Schema::table('tour_nodes', function (Blueprint $table) {
            $table->dropColumn('is_start_candidate');
        });
    }
};
