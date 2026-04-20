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
        Schema::create('items', function (Blueprint $table) {
            $table->id();

            // SKU / Item Code (unik)
            $table->string('item_code')->unique();

            // Nama produk
            $table->string('item_name');

            // isi per box (24, 12, dll)
            $table->integer('item_per_box')->default(1);

            // grouping produk
            $table->string('item_group')->nullable();

            // optional: kalau nanti mau extend
            $table->string('uom')->nullable(); // unit of measure (pcs, box, dll)
            $table->decimal('weight', 10, 2)->nullable();

            $table->text('description')->nullable();

            $table->timestamps();

            // index biar search cepat
            $table->index('item_code');
            $table->index('item_group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
