<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            $table->index(['storyline_id', 'reader_order']);
            $table->index('act_id');
            $table->index('pov_character_id');
        });

        Schema::table('chapter_versions', function (Blueprint $table) {
            $table->index(['chapter_id', 'status']);
            $table->index(['chapter_id', 'version_number']);
        });

        Schema::table('chunks', function (Blueprint $table) {
            $table->index('chapter_version_id');
            $table->index('scene_id');
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->index('book_id');
        });

        Schema::table('acts', function (Blueprint $table) {
            $table->index(['book_id', 'sort_order']);
        });

        Schema::table('plot_points', function (Blueprint $table) {
            $table->index(['book_id', 'sort_order']);
            $table->index('act_id');
        });

        Schema::table('beats', function (Blueprint $table) {
            $table->index(['plot_point_id', 'sort_order']);
        });

        Schema::table('analyses', function (Blueprint $table) {
            $table->index(['book_id', 'type']);
        });

        Schema::table('editorial_review_sections', function (Blueprint $table) {
            $table->index('editorial_review_id');
        });

        Schema::table('editorial_review_chapter_notes', function (Blueprint $table) {
            $table->index(['editorial_review_id', 'chapter_id']);
        });

        Schema::table('character_chapter', function (Blueprint $table) {
            $table->index('chapter_id');
        });

        Schema::table('wiki_entry_chapter', function (Blueprint $table) {
            $table->index('chapter_id');
        });

        Schema::table('beat_chapter', function (Blueprint $table) {
            $table->index('chapter_id');
        });

        Schema::table('character_plot_point', function (Blueprint $table) {
            $table->index('plot_point_id');
        });
    }

    public function down(): void
    {
        Schema::table('character_plot_point', function (Blueprint $table) {
            $table->dropIndex(['plot_point_id']);
        });

        Schema::table('beat_chapter', function (Blueprint $table) {
            $table->dropIndex(['chapter_id']);
        });

        Schema::table('wiki_entry_chapter', function (Blueprint $table) {
            $table->dropIndex(['chapter_id']);
        });

        Schema::table('character_chapter', function (Blueprint $table) {
            $table->dropIndex(['chapter_id']);
        });

        Schema::table('editorial_review_chapter_notes', function (Blueprint $table) {
            $table->dropIndex(['editorial_review_id', 'chapter_id']);
        });

        Schema::table('editorial_review_sections', function (Blueprint $table) {
            $table->dropIndex(['editorial_review_id']);
        });

        Schema::table('analyses', function (Blueprint $table) {
            $table->dropIndex(['book_id', 'type']);
        });

        Schema::table('beats', function (Blueprint $table) {
            $table->dropIndex(['plot_point_id', 'sort_order']);
        });

        Schema::table('plot_points', function (Blueprint $table) {
            $table->dropIndex(['book_id', 'sort_order']);
            $table->dropIndex(['act_id']);
        });

        Schema::table('acts', function (Blueprint $table) {
            $table->dropIndex(['book_id', 'sort_order']);
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->dropIndex(['book_id']);
        });

        Schema::table('chunks', function (Blueprint $table) {
            $table->dropIndex(['chapter_version_id']);
            $table->dropIndex(['scene_id']);
        });

        Schema::table('chapter_versions', function (Blueprint $table) {
            $table->dropIndex(['chapter_id', 'status']);
            $table->dropIndex(['chapter_id', 'version_number']);
        });

        Schema::table('chapters', function (Blueprint $table) {
            $table->dropIndex(['storyline_id', 'reader_order']);
            $table->dropIndex(['act_id']);
            $table->dropIndex(['pov_character_id']);
        });
    }
};
