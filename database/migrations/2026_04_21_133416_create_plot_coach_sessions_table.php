<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plot_coach_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('agent_conversation_id', 36);
            $table->string('status');
            $table->string('stage');
            $table->string('coaching_mode')->nullable();
            $table->json('decisions')->nullable();
            $table->json('pending_board_changes')->nullable();
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->integer('cost_cents')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->foreign('agent_conversation_id')
                ->references('id')
                ->on('agent_conversations')
                ->restrictOnDelete();
        });

        DB::statement("CREATE UNIQUE INDEX plot_coach_sessions_book_active_unique ON plot_coach_sessions(book_id) WHERE status = 'active'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS plot_coach_sessions_book_active_unique');
        Schema::dropIfExists('plot_coach_sessions');
    }
};
