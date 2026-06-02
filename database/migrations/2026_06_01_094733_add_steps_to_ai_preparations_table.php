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
            $table->json('steps')->nullable()->after('current_phase_progress');
            $table->unsignedInteger('total_phases')->nullable()->after('steps');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_preparations', function (Blueprint $table) {
            $table->dropColumn(['steps', 'total_phases']);
        });
    }
};
