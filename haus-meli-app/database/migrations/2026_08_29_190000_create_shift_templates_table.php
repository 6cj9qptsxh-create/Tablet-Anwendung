<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('color', 50)->default('#2563eb');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('shift_templates')->insert([
            [
                'name' => 'Frühschicht',
                'start_time' => '07:00:00',
                'end_time' => '16:00:00',
                'color' => '#2563eb',
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Spätschicht',
                'start_time' => '14:00:00',
                'end_time' => '23:00:00',
                'color' => '#c2410c',
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Tagdienst',
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'color' => '#15803d',
                'sort_order' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_templates');
    }
};
