<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mappings', function (Blueprint $table) {
            $table->foreignId('mapping_report_id')
                ->nullable()
                ->after('agent_report_id');

            $table->foreign('mapping_report_id')
                ->references('id')
                ->on('mappings_report')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mappings', function (Blueprint $table) {
            $table->dropForeign(['mapping_report_id']);
            $table->dropColumn('mapping_report_id');
        });
    }
};