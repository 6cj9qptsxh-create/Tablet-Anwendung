<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Der Admin legt nach erfolgreichem Setzen in Omada eine Zeile an:
 *
 * INSERT INTO ferienwohnung_laravel.guest_wifi (ssid, password, updated_at)
 * VALUES ('Netzname', 'neues-passwort', NOW());
 *
 * Die Infoseite zeigt die neueste Zeile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_wifi', function (Blueprint $table) {
            $table->id();
            $table->string('ssid', 64);
            $table->string('password', 128);
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_wifi');
    }
};
