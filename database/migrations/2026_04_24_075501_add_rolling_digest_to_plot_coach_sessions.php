<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plot_coach_sessions', function (Blueprint $table) {
            $table->text('rolling_digest')->nullable();
            $table->unsignedInteger('rolling_digest_through_turn')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('plot_coach_sessions', function (Blueprint $table) {
            $table->dropColumn(['rolling_digest', 'rolling_digest_through_turn']);
        });
    }
};
