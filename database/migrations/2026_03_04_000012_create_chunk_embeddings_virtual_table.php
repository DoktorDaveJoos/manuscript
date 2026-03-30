<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chunks', function (Blueprint $table) {
            $table->dropColumn('embedding');
        });

        try {
            DB::statement(<<<'SQL'
                CREATE VIRTUAL TABLE IF NOT EXISTS chunk_embeddings USING vec0(
                    chunk_id INTEGER PRIMARY KEY,
                    embedding float[1536]
                )
            SQL);
        } catch (Throwable $e) {
            Log::warning('sqlite-vec: Could not create chunk_embeddings virtual table. Is the extension loaded?', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function down(): void
    {
        try {
            DB::statement('DROP TABLE IF EXISTS chunk_embeddings');
        } catch (Throwable) {
            // Virtual table may not exist if extension wasn't loaded
        }

        Schema::table('chunks', function (Blueprint $table) {
            $table->binary('embedding')->nullable()->after('position');
        });
    }
};
