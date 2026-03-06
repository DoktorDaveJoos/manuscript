<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('writing_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('words_written')->default(0);
            $table->boolean('goal_met')->default(false);
            $table->timestamps();

            $table->unique(['book_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('writing_sessions');
    }
};
