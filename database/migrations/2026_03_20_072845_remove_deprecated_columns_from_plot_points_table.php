<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plot_points', function (Blueprint $table) {
            $table->dropForeign(['storyline_id']);
            $table->dropForeign(['intended_chapter_id']);
            $table->dropForeign(['actual_chapter_id']);
            $table->dropColumn(['storyline_id', 'intended_chapter_id', 'actual_chapter_id', 'is_ai_derived', 'tension_score']);
        });
    }

    public function down(): void
    {
        Schema::table('plot_points', function (Blueprint $table) {
            $table->foreignId('storyline_id')->nullable()->constrained()->nullOnDelete()->after('book_id');
            $table->foreignId('intended_chapter_id')->nullable()->constrained('chapters')->nullOnDelete()->after('act_id');
            $table->foreignId('actual_chapter_id')->nullable()->constrained('chapters')->nullOnDelete()->after('intended_chapter_id');
            $table->boolean('is_ai_derived')->default(false)->after('sort_order');
            $table->unsignedTinyInteger('tension_score')->nullable()->after('is_ai_derived');
        });
    }
};
