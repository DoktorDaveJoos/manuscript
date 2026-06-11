<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Writing style and prose pass rules become book-only settings: copy the
     * global app_settings values into books that have none of their own, then
     * drop the global keys. Data movement only — no schema change.
     */
    public function up(): void
    {
        $globalStyle = DB::table('app_settings')->where('key', 'writing_style_text')->value('value');

        if (filled($globalStyle)) {
            DB::table('books')
                ->where(fn ($q) => $q->whereNull('writing_style_text')->orWhere('writing_style_text', ''))
                ->update(['writing_style_text' => $globalStyle]);
        }

        $globalRules = DB::table('app_settings')->where('key', 'prose_pass_rules')->value('value');

        if (filled($globalRules)) {
            DB::table('books')
                ->whereNull('prose_pass_rules')
                ->update(['prose_pass_rules' => $globalRules]);
        }

        DB::table('app_settings')->whereIn('key', ['writing_style_text', 'prose_pass_rules'])->delete();
    }

    public function down(): void
    {
        // Data movement only — the copied values stay on the books.
    }
};
