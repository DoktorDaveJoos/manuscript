<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wiki_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('name');
            $table->string('type')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('first_appearance')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_ai_extracted')->default(false);
            $table->timestamps();

            $table->index(['book_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wiki_entries');
    }
};
