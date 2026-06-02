<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plot_coach_sessions', function (Blueprint $table) {
            $table->unsignedInteger('user_turn_count')->default(0);
            $table->text('archive_summary')->nullable();
            $table->unsignedBigInteger('parent_session_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('plot_coach_sessions', function (Blueprint $table) {
            $table->dropColumn(['user_turn_count', 'archive_summary', 'parent_session_id']);
        });
    }
};
