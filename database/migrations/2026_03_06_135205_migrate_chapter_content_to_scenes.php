<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $chapters = DB::table('chapters')->get();

        foreach ($chapters as $chapter) {
            $currentVersion = DB::table('chapter_versions')
                ->where('chapter_id', $chapter->id)
                ->where('is_current', true)
                ->first();

            DB::table('scenes')->insert([
                'chapter_id' => $chapter->id,
                'title' => 'Scene 1',
                'content' => $currentVersion?->content,
                'sort_order' => 0,
                'word_count' => $chapter->word_count,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Scenes table will be dropped by the create_scenes_table rollback
    }
};
