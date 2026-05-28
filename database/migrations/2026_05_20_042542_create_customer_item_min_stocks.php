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
        Schema::create('customer_item_min_stocks', function (Blueprint $table) {
            $table->id();

            // customer dari users table
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // item
            $table->foreignId('item_id')
                ->constrained('items')
                ->cascadeOnDelete();

            // minimum stock
            $table->integer('minimum_stock')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_item_min_stocks');
    }
};
