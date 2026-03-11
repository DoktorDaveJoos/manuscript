<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Reset prepared_content_hash for chapters that were marked as prepared
     * but never actually analyzed (no analyzed_at timestamp).
     */
    public function up(): void
    {
        DB::table('chapters')
            ->whereNull('analyzed_at')
            ->whereNotNull('prepared_content_hash')
            ->update(['prepared_content_hash' => null, 'ai_prepared_at' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot restore original hashes — they were stale/incorrect anyway
    }
};
