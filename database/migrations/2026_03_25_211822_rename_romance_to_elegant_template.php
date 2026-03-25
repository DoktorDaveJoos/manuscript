<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('books')
            ->where('export_template', 'romance')
            ->update(['export_template' => 'elegant']);
    }

    public function down(): void
    {
        DB::table('books')
            ->where('export_template', 'elegant')
            ->update(['export_template' => 'romance']);
    }
};
