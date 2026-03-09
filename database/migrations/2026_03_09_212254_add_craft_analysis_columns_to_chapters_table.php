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
        Schema::table('chapters', function (Blueprint $table) {
            $table->string('scene_purpose')->nullable()->after('hook_type');
            $table->string('value_shift')->nullable()->after('scene_purpose');
            $table->string('emotional_state_open')->nullable()->after('value_shift');
            $table->string('emotional_state_close')->nullable()->after('emotional_state_open');
            $table->tinyInteger('emotional_shift_magnitude')->nullable()->after('emotional_state_close');
            $table->tinyInteger('micro_tension_score')->nullable()->after('emotional_shift_magnitude');
            $table->string('pacing_feel')->nullable()->after('micro_tension_score');
            $table->tinyInteger('entry_hook_score')->nullable()->after('pacing_feel');
            $table->tinyInteger('exit_hook_score')->nullable()->after('entry_hook_score');
            $table->tinyInteger('sensory_grounding')->nullable()->after('exit_hook_score');
            $table->string('information_delivery')->nullable()->after('sensory_grounding');
        });
    }

    public function down(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            $table->dropColumn([
                'scene_purpose',
                'value_shift',
                'emotional_state_open',
                'emotional_state_close',
                'emotional_shift_magnitude',
                'micro_tension_score',
                'pacing_feel',
                'entry_hook_score',
                'exit_hook_score',
                'sensory_grounding',
                'information_delivery',
            ]);
        });
    }
};
