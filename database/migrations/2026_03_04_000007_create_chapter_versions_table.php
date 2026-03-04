<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapter_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number')->default(1);
            $table->longText('content')->nullable();
            $table->string('source')->default('original');
            $table->text('change_summary')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->index(['chapter_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapter_versions');
    }
};
