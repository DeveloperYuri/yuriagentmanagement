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
        Schema::create('agent_export_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('kode_sku_agent')
                ->nullable();

            $table->string('kode_sku_jim')
                ->nullable();

            $table->text('item_name_jim')
                ->nullable();

            $table->decimal('stock_karton', 18, 2)
                ->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_export_stocks');
    }
};
