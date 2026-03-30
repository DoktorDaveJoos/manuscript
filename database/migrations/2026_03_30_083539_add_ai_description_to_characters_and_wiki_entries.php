<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->text('ai_description')->nullable()->after('description');
        });

        Schema::table('wiki_entries', function (Blueprint $table) {
            $table->text('ai_description')->nullable()->after('description');
        });

        // Move AI-extracted descriptions to ai_description column
        DB::table('characters')
            ->where('is_ai_extracted', true)
            ->whereNotNull('description')
            ->update([
                'ai_description' => DB::raw('description'),
                'description' => null,
            ]);

        DB::table('wiki_entries')
            ->where('is_ai_extracted', true)
            ->whereNotNull('description')
            ->update([
                'ai_description' => DB::raw('description'),
                'description' => null,
            ]);
    }

    public function down(): void
    {
        // Move ai_description back to description where description is null
        DB::table('characters')
            ->whereNull('description')
            ->whereNotNull('ai_description')
            ->update([
                'description' => DB::raw('ai_description'),
            ]);

        DB::table('wiki_entries')
            ->whereNull('description')
            ->whereNotNull('ai_description')
            ->update([
                'description' => DB::raw('ai_description'),
            ]);

        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('ai_description');
        });

        Schema::table('wiki_entries', function (Blueprint $table) {
            $table->dropColumn('ai_description');
        });
    }
};
