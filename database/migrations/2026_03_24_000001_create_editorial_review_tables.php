<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->json('progress')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('overall_score')->nullable();
            $table->text('executive_summary')->nullable();
            $table->json('top_strengths')->nullable();
            $table->json('top_improvements')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('editorial_review_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('editorial_review_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->unsignedTinyInteger('score')->nullable();
            $table->text('summary')->nullable();
            $table->json('findings')->nullable();
            $table->json('recommendations')->nullable();
            $table->timestamps();
        });

        Schema::create('editorial_review_chapter_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('editorial_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->json('notes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_review_chapter_notes');
        Schema::dropIfExists('editorial_review_sections');
        Schema::dropIfExists('editorial_reviews');
    }
};
