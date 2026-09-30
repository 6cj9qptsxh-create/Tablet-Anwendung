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
            $table->json('start_kinds')->nullable()->after('is_start_candidate');
        });

        // Bestehende Startkandidaten befüllen
        if (Schema::hasTable('tour_nodes')) {
            $rows = DB::table('tour_nodes')->select('id', 'name', 'is_start_candidate')->get();
            foreach ($rows as $row) {
                if (! $row->is_start_candidate) {
                    continue;
                }
                $name = mb_strtolower((string) $row->name);
                $kinds = [];
                if (str_contains($name, 'meli') || str_contains($name, 'ferien')) {
                    $kinds[] = 'ferienwohnung';
                }
                if (str_contains($name, 'bus') || str_contains($name, 'halt')) {
                    $kinds[] = 'bus';
                }
                if (str_contains($name, 'park') || str_contains($name, 'auto') || str_contains($name, 'pkw')) {
                    $kinds[] = 'auto';
                }
                // Typischer Tal-Zugang ohne Keyword: Bus + Auto
                if ($kinds === []) {
                    $kinds = ['bus', 'auto'];
                }
                DB::table('tour_nodes')->where('id', $row->id)->update([
                    'start_kinds' => json_encode(array_values(array_unique($kinds))),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('tour_nodes', function (Blueprint $table) {
            $table->dropColumn('start_kinds');
        });
    }
};
