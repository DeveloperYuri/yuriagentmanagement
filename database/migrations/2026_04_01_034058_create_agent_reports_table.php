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
        Schema::create('agent_reports', function (Blueprint $table) {
            $table->id();
            
            // Relasi ke tabel agents
            // constrained() otomatis merujuk ke tabel 'agents'
            // onDelete('cascade') artinya jika agent dihapus, laporannya ikut terhapus
            $table->foreignId('agent_id')->constrained()->onDelete('cascade');

            // Periode Laporan
            $table->integer('month'); // 1 - 12
            $table->integer('year');  // Contoh: 2026
            
            // File Handling
            $table->string('file_path'); // Path lokasi file di storage
            $table->string('file_name'); // Nama asli file pas diupload (opsional, buat display)

            // Status Verifikasi (Opsional tapi berguna)
            // $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->text('notes')->nullable(); // Catatan jika admin reject laporan

            $table->timestamps();

            // Tambahkan Index Gabungan agar pengecekan laporan ganda lebih cepat
            // Dan pencarian per periode lebih kencang di PostgreSQL
            $table->index(['agent_id', 'month', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_reports');
    }
};
