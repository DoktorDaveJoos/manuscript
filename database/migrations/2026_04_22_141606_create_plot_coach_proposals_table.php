<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plot_coach_proposals', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('session_id')
                ->constrained('plot_coach_sessions')
                ->cascadeOnDelete();
            $table->string('kind', 32); // batch | chapter_plan
            $table->json('writes');
            $table->text('summary');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('applied_batch_id')
                ->nullable()
                ->constrained('plot_coach_batches')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['session_id', 'approved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plot_coach_proposals');
    }
};
