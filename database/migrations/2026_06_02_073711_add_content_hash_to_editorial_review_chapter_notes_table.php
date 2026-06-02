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
        Schema::table('editorial_review_chapter_notes', function (Blueprint $table) {
            // The chapter content_hash at the time the note was generated, so a
            // later review can reuse the note instead of re-calling the AI when
            // the chapter is unchanged.
            $table->string('content_hash')->nullable()->after('chapter_id');
            $table->index(['chapter_id', 'content_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('editorial_review_chapter_notes', function (Blueprint $table) {
            $table->dropIndex(['chapter_id', 'content_hash']);
            $table->dropColumn('content_hash');
        });
    }
};
