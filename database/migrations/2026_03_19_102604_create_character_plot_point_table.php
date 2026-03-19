<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_plot_point', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plot_point_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('key');
            $table->timestamps();

            $table->unique(['character_id', 'plot_point_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_plot_point');
    }
};
