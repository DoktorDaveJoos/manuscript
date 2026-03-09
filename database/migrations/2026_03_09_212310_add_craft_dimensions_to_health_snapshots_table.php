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
        Schema::table('health_snapshots', function (Blueprint $table) {
            $table->unsignedTinyInteger('scene_purpose_score')->nullable()->after('weave_score');
            $table->unsignedTinyInteger('tension_dynamics_score')->nullable()->after('scene_purpose_score');
            $table->unsignedTinyInteger('emotional_arc_score')->nullable()->after('tension_dynamics_score');
            $table->unsignedTinyInteger('craft_score')->nullable()->after('emotional_arc_score');
        });
    }

    public function down(): void
    {
        Schema::table('health_snapshots', function (Blueprint $table) {
            $table->dropColumn([
                'scene_purpose_score',
                'tension_dynamics_score',
                'emotional_arc_score',
                'craft_score',
            ]);
        });
    }
};
