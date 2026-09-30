<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tour_nodes')) {
            return;
        }
        Schema::table('tour_nodes', function (Blueprint $table) {
            if (! Schema::hasColumn('tour_nodes', 'is_dead_end')) {
                $table->boolean('is_dead_end')->default(false)->after('is_highlight');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tour_nodes')) {
            return;
        }
        Schema::table('tour_nodes', function (Blueprint $table) {
            if (Schema::hasColumn('tour_nodes', 'is_dead_end')) {
                $table->dropColumn('is_dead_end');
            }
        });
    }
};
