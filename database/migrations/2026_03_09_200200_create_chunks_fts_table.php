<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE VIRTUAL TABLE IF NOT EXISTS chunks_fts USING fts5(
                content,
                content='chunks',
                content_rowid='id'
            )
        SQL);

        // Sync triggers: keep FTS index in sync with chunks table
        DB::statement(<<<'SQL'
            CREATE TRIGGER IF NOT EXISTS chunks_ai AFTER INSERT ON chunks BEGIN
                INSERT INTO chunks_fts(rowid, content) VALUES (new.id, new.content);
            END
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER IF NOT EXISTS chunks_ad AFTER DELETE ON chunks BEGIN
                INSERT INTO chunks_fts(chunks_fts, rowid, content) VALUES('delete', old.id, old.content);
            END
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER IF NOT EXISTS chunks_au AFTER UPDATE ON chunks BEGIN
                INSERT INTO chunks_fts(chunks_fts, rowid, content) VALUES('delete', old.id, old.content);
                INSERT INTO chunks_fts(rowid, content) VALUES (new.id, new.content);
            END
        SQL);

        // Populate from existing data
        DB::statement(<<<'SQL'
            INSERT INTO chunks_fts(rowid, content) SELECT id, content FROM chunks
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS chunks_ai');
        DB::statement('DROP TRIGGER IF EXISTS chunks_ad');
        DB::statement('DROP TRIGGER IF EXISTS chunks_au');
        DB::statement('DROP TABLE IF EXISTS chunks_fts');
    }
};
