<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Proofreading becomes a book-only setting: add the column, copy the
     * global app_settings value into books that have none of their own, then
     * drop the global key. The column guard keeps up() idempotent so tests
     * can re-run the data move against an already-migrated schema.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('books', 'proofreading_config')) {
            Schema::table('books', function (Blueprint $table) {
                $table->text('proofreading_config')->nullable();
            });
        }

        $globalConfig = DB::table('app_settings')->where('key', 'proofreading_config')->value('value');

        if (filled($globalConfig)) {
            DB::table('books')
                ->whereNull('proofreading_config')
                ->update(['proofreading_config' => $globalConfig]);
        }

        DB::table('app_settings')->where('key', 'proofreading_config')->delete();
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn('proofreading_config');
        });
    }
};
