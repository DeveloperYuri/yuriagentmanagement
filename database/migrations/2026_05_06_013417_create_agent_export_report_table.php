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
        Schema::create('agent_export_report', function (Blueprint $table) {
            $table->id();
            $table->string('nama_agen')->nullable();
            $table->string('kode_customer')->nullable();
            $table->string('nama_customer')->nullable();
            $table->text('alamat_customer')->nullable();
            $table->string('nomor_telepon_customer')->nullable();

            $table->string('invoice_nomor_agen')->nullable();
            $table->date('tanggal_invoice')->nullable();

            $table->string('tipe_customer')->nullable();
            $table->string('sales')->nullable();

            $table->string('sku_kode_agen')->nullable();
            $table->string('nama_sku')->nullable();

            $table->decimal('qty_terjual_pcs', 18, 2)->default(0);

            $table->decimal('diskon_1_reguler', 10, 2)->default(0);
            $table->decimal('diskon_2_cash', 10, 2)->default(0);
            $table->decimal('diskon_3_dc_free', 10, 2)->default(0);
            $table->decimal('diskon_4_promo_1', 10, 2)->default(0);
            $table->decimal('diskon_5_promo_2', 10, 2)->default(0);
            $table->decimal('diskon_6_rp', 18, 2)->default(0);

            $table->decimal('quantity_bonus', 18, 2)->default(0);
            $table->decimal('rafraksi', 18, 2)->default(0);

            $table->decimal('total_invoice_value', 18, 2)->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_export_report');
    }
};
