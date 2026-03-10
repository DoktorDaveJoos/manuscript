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
            $table->string('content_hash', 32)->nullable()->after('word_count');
            $table->string('prepared_content_hash', 32)->nullable()->after('content_hash');
            $table->timestamp('ai_prepared_at')->nullable()->after('prepared_content_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            $table->dropColumn(['content_hash', 'prepared_content_hash', 'ai_prepared_at']);
        });
    }
};
