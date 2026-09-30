<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->date('delivery_date');
            $table->string('channel', 16); // guest | owner
            $table->string('status', 16)->default('open'); // open | locked
            $table->json('items');
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();

            $table->unique(['delivery_date', 'channel']);
            $table->index(['channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
