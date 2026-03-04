<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plot_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('storyline_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('act_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('setup');
            $table->foreignId('intended_chapter_id')->nullable()->constrained('chapters')->nullOnDelete();
            $table->foreignId('actual_chapter_id')->nullable()->constrained('chapters')->nullOnDelete();
            $table->string('status')->default('planned');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_ai_derived')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plot_points');
    }
};
