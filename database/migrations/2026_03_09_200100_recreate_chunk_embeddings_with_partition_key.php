<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::statement('DROP TABLE IF EXISTS chunk_embeddings');
        } catch (Throwable) {
            // Virtual table may not exist
        }

        try {
            DB::statement(<<<'SQL'
                CREATE VIRTUAL TABLE IF NOT EXISTS chunk_embeddings USING vec0(
                    book_id INTEGER PARTITION KEY,
                    chunk_id INTEGER PRIMARY KEY,
                    embedding float[1536]
                )
            SQL);
        } catch (Throwable $e) {
            Log::warning('sqlite-vec: Could not create chunk_embeddings virtual table with partition key.', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function down(): void
    {
        try {
            DB::statement('DROP TABLE IF EXISTS chunk_embeddings');
        } catch (Throwable) {
            // Virtual table may not exist
        }

        try {
            DB::statement(<<<'SQL'
                CREATE VIRTUAL TABLE IF NOT EXISTS chunk_embeddings USING vec0(
                    chunk_id INTEGER PRIMARY KEY,
                    embedding float[1536]
                )
            SQL);
        } catch (Throwable $e) {
            Log::warning('sqlite-vec: Could not recreate chunk_embeddings without partition key.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
};
