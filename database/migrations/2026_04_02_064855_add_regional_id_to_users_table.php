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
        Schema::table('users', function (Blueprint $table) {
            // Menambahkan kolom regional_id setelah kolom email atau id
            // constrained() otomatis mencari tabel 'regionals'
            // nullOnDelete() supaya kalau regional dihapus, usernya tidak ikut terhapus (opsional)
            $table->foreignId('regional_id')
                ->nullable()
                ->after('email')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['regional_id']);
            $table->dropColumn('regional_id');
        });
    }
};
