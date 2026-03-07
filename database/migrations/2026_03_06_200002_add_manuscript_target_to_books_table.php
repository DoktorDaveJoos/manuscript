<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->unsignedInteger('target_word_count')->nullable()->after('daily_word_count_goal');
            $table->timestamp('milestone_reached_at')->nullable()->after('target_word_count');
            $table->boolean('milestone_dismissed')->default(false)->after('milestone_reached_at');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['target_word_count', 'milestone_reached_at', 'milestone_dismissed']);
        });
    }
};
