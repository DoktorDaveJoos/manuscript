<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('composite_score');
            $table->unsignedTinyInteger('hooks_score');
            $table->unsignedTinyInteger('pacing_score');
            $table->unsignedTinyInteger('tension_score');
            $table->unsignedTinyInteger('weave_score');
            $table->date('recorded_at');
            $table->timestamps();

            $table->unique(['book_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_snapshots');
    }
};
