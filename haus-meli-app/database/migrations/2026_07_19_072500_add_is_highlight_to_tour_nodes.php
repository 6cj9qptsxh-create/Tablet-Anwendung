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
            $table->boolean('is_highlight')->default(false)->after('start_kinds');
        });

        // Bekannte Highlight-Orte (ehemals Node-Flags in den Splits)
        $names = [
            'Durrich Alpe',
            'Lichtsee',
            'Blankasee',
            'Edmund-Graf-Hütte',
            'Hoher Riffler',
            'Hohes Rad',
            'Kreuzjochspitze',
            'Visnitz Wasserfall',
            'Silvretta Stausee Parkplatz',
        ];
        DB::table('tour_nodes')->whereIn('name', $names)->update(['is_highlight' => true]);
    }

    public function down(): void
    {
        Schema::table('tour_nodes', function (Blueprint $table) {
            $table->dropColumn('is_highlight');
        });
    }
};
