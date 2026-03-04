<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('storyline_id')->constrained()->cascadeOnDelete();
            $table->foreignId('act_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->foreignId('pov_character_id')->nullable()->constrained('characters')->nullOnDelete();
            $table->string('timeline_position')->nullable();
            $table->unsignedInteger('reader_order')->default(0);
            $table->string('status')->default('draft');
            $table->unsignedInteger('word_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapters');
    }
};
