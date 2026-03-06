<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('content')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('word_count')->default(0);
            $table->timestamps();

            $table->index(['chapter_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenes');
    }
};
