<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beat_chapter', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['beat_id', 'chapter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beat_chapter');
    }
};
