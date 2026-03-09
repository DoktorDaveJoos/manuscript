<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wiki_entry_chapter', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wiki_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['wiki_entry_id', 'chapter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wiki_entry_chapter');
    }
};
