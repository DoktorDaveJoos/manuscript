<?php

use App\Support\WordCount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Stored word counts were computed with str_word_count(), which is
     * ASCII-bound: words with umlauts/accents were counted multiple times
     * and numbers were not counted at all. Recompute every scene and
     * chapter count with the Unicode-aware WordCount algorithm.
     */
    public function up(): void
    {
        DB::table('scenes')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->select('id', 'content', 'word_count')
            ->chunkById(200, function ($scenes) {
                foreach ($scenes as $scene) {
                    $wordCount = WordCount::count($scene->content ?? '');

                    if ($wordCount !== (int) $scene->word_count) {
                        DB::table('scenes')
                            ->where('id', $scene->id)
                            ->update(['word_count' => $wordCount]);
                    }
                }
            });

        $sums = DB::table('scenes')
            ->whereNull('deleted_at')
            ->groupBy('chapter_id')
            ->selectRaw('chapter_id, SUM(word_count) as total')
            ->pluck('total', 'chapter_id');

        DB::table('chapters')
            ->orderBy('id')
            ->select('id', 'word_count')
            ->chunkById(200, function ($chapters) use ($sums) {
                foreach ($chapters as $chapter) {
                    $wordCount = (int) ($sums[$chapter->id] ?? 0);

                    if ($wordCount !== (int) $chapter->word_count) {
                        DB::table('chapters')
                            ->where('id', $chapter->id)
                            ->update(['word_count' => $wordCount]);
                    }
                }
            });
    }

    /**
     * Data recalculation — intentionally irreversible. The old counts were
     * wrong; there is nothing to restore.
     */
    public function down(): void
    {
        //
    }
};
