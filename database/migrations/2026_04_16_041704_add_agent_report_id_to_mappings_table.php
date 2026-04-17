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
        Schema::table('mappings', function (Blueprint $table) {
            $table->foreignId('agent_report_id')
                ->nullable() // Izinkan null agar data lama tidak error
                ->after('id')
                ->constrained('agent_reports')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mappings', function (Blueprint $table) {
            //
        });
    }
};
