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
        Schema::table('ai_preparations', function (Blueprint $table) {
            $table->string('current_phase')->nullable()->after('status');
            $table->unsignedInteger('current_phase_total')->default(0)->after('current_phase');
            $table->unsignedInteger('current_phase_progress')->default(0)->after('current_phase_total');
            $table->json('completed_phases')->nullable()->after('embedded_chunks');
            $table->json('phase_errors')->nullable()->after('completed_phases');
        });
    }

    public function down(): void
    {
        Schema::table('ai_preparations', function (Blueprint $table) {
            $table->dropColumn(['current_phase', 'current_phase_total', 'current_phase_progress', 'completed_phases', 'phase_errors']);
        });
    }
};
