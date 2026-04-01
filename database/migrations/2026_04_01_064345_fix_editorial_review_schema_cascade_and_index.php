<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editorial_review_chapter_notes', function (Blueprint $table) {
            $table->dropForeign(['chapter_id']);
            $table->foreign('chapter_id')
                ->references('id')
                ->on('chapters')
                ->cascadeOnDelete();
        });

        Schema::table('editorial_reviews', function (Blueprint $table) {
            $table->index(['book_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('editorial_reviews', function (Blueprint $table) {
            $table->dropIndex(['book_id', 'status']);
        });

        Schema::table('editorial_review_chapter_notes', function (Blueprint $table) {
            $table->dropForeign(['chapter_id']);
            $table->foreign('chapter_id')
                ->references('id')
                ->on('chapters');
        });
    }
};
