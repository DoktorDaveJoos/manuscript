<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_review_chapter_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('editorial_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chapter_id')->constrained();
            $table->json('notes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_review_chapter_notes');
    }
};
