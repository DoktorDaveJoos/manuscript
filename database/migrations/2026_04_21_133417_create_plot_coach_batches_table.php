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
        Schema::create('plot_coach_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')
                ->constrained('plot_coach_sessions')
                ->cascadeOnDelete();
            $table->text('summary');
            $table->json('payload');
            $table->timestamp('applied_at');
            $table->timestamp('reverted_at')->nullable();
            $table->timestamp('undo_window_expires_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plot_coach_batches');
    }
};
