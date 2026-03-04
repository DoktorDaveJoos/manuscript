<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_chapter', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('mentioned');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['character_id', 'chapter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_chapter');
    }
};
