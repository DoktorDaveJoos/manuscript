<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The standalone AI preparation feature was folded into editorial review:
     * the review run now refreshes embeddings, writing style, and chapter
     * analysis itself. Preparation bookkeeping, health snapshots, the story
     * bible, and analyses of types nothing reads anymore go away. Guarded so
     * the per-launch NativePHP `migrate --force` succeeds on any DB state.
     */
    public function up(): void
    {
        Schema::dropIfExists('ai_preparations');
        Schema::dropIfExists('health_snapshots');

        if (Schema::hasColumn('books', 'story_bible')) {
            Schema::table('books', function (Blueprint $table) {
                $table->dropColumn('story_bible');
            });
        }

        // Rows of removed AnalysisType cases would break the enum cast on read.
        DB::table('analyses')
            ->whereIn('type', ['pacing', 'density', 'chapter_hook', 'scene_audit'])
            ->delete();
    }

    public function down(): void
    {
        // Irreversible: the AI preparation feature was removed. Restoring it
        // means restoring the original create migrations alongside the code.
    }
};
