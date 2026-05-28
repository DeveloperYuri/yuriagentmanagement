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
        Schema::create('agent_item_mappings', function (Blueprint $table) {
            $table->id();

            $table->string('agent_sku')->unique();

            $table->string('item_code');
            $table->text('item_name')->nullable();

            $table->integer('item_per_box')->nullable();

            $table->string('item_group')->nullable();

            $table->timestamps();

            $table->index('item_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_item_mappings');
    }
};