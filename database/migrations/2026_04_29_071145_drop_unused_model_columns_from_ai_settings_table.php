<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the per-task model override columns and rename `text_model` to
     * `azure_deployment`. Authors do not pick AI models — the SDK selects the
     * smartest/cheapest model per agent and tracks new releases automatically
     * on `composer update laravel/ai`. The Azure-specific deployment override
     * survives because Azure deployments are tenant-specific and have no SDK
     * default the user can fall back to.
     */
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->renameColumn('text_model', 'azure_deployment');
        });

        Schema::table('ai_settings', function (Blueprint $table) {
            $table->dropColumn(['writing_model', 'analysis_model', 'extraction_model']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->renameColumn('azure_deployment', 'text_model');
        });

        Schema::table('ai_settings', function (Blueprint $table) {
            $table->string('writing_model')->nullable()->after('text_model');
            $table->string('analysis_model')->nullable()->after('writing_model');
            $table->string('extraction_model')->nullable()->after('analysis_model');
        });
    }
};
