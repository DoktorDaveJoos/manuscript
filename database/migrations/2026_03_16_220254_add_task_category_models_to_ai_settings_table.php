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
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->string('writing_model')->nullable()->after('text_model');
            $table->string('analysis_model')->nullable()->after('writing_model');
            $table->string('extraction_model')->nullable()->after('analysis_model');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->dropColumn(['writing_model', 'analysis_model', 'extraction_model']);
        });
    }
};
