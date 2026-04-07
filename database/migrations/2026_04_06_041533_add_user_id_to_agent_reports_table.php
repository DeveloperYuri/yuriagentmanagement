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
        Schema::table('agent_reports', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->after('id') // Letakkan setelah ID agar rapi
                ->constrained('users')
                ->cascadeOnDelete(); // Jika user dihapus, laporannya ikut hapus (opsional)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_reports', function (Blueprint $table) {
            //
        });
    }
};
