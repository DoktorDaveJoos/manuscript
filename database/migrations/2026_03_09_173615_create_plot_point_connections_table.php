<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plot_point_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_plot_point_id')->constrained('plot_points')->cascadeOnDelete();
            $table->foreignId('target_plot_point_id')->constrained('plot_points')->cascadeOnDelete();
            $table->string('type')->default('causes');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['source_plot_point_id', 'target_plot_point_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plot_point_connections');
    }
};
