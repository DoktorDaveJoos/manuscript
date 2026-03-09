<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            if (! Schema::hasColumn('books', 'daily_word_count_goal')) {
                $table->unsignedInteger('daily_word_count_goal')->nullable();
            }
            if (! Schema::hasColumn('books', 'story_bible')) {
                $table->json('story_bible')->nullable();
            }
        });

        Schema::table('chapters', function (Blueprint $table) {
            if (! Schema::hasColumn('chapters', 'notes')) {
                $table->text('notes')->nullable();
            }
            if (! Schema::hasColumn('chapters', 'summary')) {
                $table->text('summary')->nullable();
            }
            if (! Schema::hasColumn('chapters', 'tension_score')) {
                $table->tinyInteger('tension_score')->nullable();
            }
            if (! Schema::hasColumn('chapters', 'hook_score')) {
                $table->tinyInteger('hook_score')->nullable();
            }
            if (! Schema::hasColumn('chapters', 'hook_type')) {
                $table->string('hook_type')->nullable();
            }
        });
    }

    public function down(): void
    {
        // No-op: these columns are owned by their original migrations.
    }
};
