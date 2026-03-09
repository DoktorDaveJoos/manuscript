<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('chapter_versions', function (Blueprint $table) {
            $table->json('scene_map')->nullable()->after('change_summary');
        });
    }

    public function down(): void
    {
        Schema::table('chapter_versions', function (Blueprint $table) {
            $table->dropColumn('scene_map');
        });
    }
};
