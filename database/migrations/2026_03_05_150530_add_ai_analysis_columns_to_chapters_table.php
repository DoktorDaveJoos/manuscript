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
        Schema::table('chapters', function (Blueprint $table) {
            $table->text('summary')->nullable()->after('word_count');
            $table->tinyInteger('tension_score')->nullable()->after('summary');
            $table->tinyInteger('hook_score')->nullable()->after('tension_score');
            $table->string('hook_type')->nullable()->after('hook_score');
        });
    }

    public function down(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            $table->dropColumn(['summary', 'tension_score', 'hook_score', 'hook_type']);
        });
    }
};
