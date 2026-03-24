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
            $table->integer('overall_score')->nullable();
            $table->text('executive_summary')->nullable();
            $table->json('top_strengths')->nullable();
            $table->json('top_improvements')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_reviews');
    }
};
