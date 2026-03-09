<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chunks', function (Blueprint $table) {
            $table->foreignId('scene_id')->nullable()->after('chapter_version_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chunks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scene_id');
        });
    }
};
