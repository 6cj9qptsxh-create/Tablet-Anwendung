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
            $table->boolean('offers_food')->default(false)->after('is_highlight');
            $table->boolean('offers_lodging')->default(false)->after('offers_food');
            $table->string('season_open')->nullable()->after('offers_lodging');
        });

        // Bekannte Hütten
        DB::table('tour_nodes')->where('name', 'Edmund-Graf-Hütte')->update([
            'offers_food' => true,
            'offers_lodging' => true,
            'season_open' => '19.06. – 27.09.',
        ]);
    }

    public function down(): void
    {
        Schema::table('tour_nodes', function (Blueprint $table) {
            $table->dropColumn(['offers_food', 'offers_lodging', 'season_open']);
        });
    }
};
