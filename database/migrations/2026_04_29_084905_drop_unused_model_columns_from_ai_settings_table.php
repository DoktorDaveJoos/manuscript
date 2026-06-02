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
     *
     * The up()/down() are idempotent because the previous attempt at this
     * migration ran on existing dev/native DBs but its file was wiped by a
     * concurrent-session reset — this migration must succeed both on those
     * already-renamed DBs and on freshly-cloned ones.
     */
    public function up(): void
    {
        if (Schema::hasColumn('ai_settings', 'text_model')) {
            Schema::table('ai_settings', function (Blueprint $table) {
                $table->renameColumn('text_model', 'azure_deployment');
            });
        }

        $columnsToDrop = array_values(array_filter(
            ['writing_model', 'analysis_model', 'extraction_model'],
            fn ($col) => Schema::hasColumn('ai_settings', $col),
        ));

        if (filled($columnsToDrop)) {
            Schema::table('ai_settings', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ai_settings', 'azure_deployment')) {
            Schema::table('ai_settings', function (Blueprint $table) {
                $table->renameColumn('azure_deployment', 'text_model');
            });
        }

        $columnsToAdd = array_values(array_filter(
            ['writing_model', 'analysis_model', 'extraction_model'],
            fn ($col) => ! Schema::hasColumn('ai_settings', $col),
        ));

        if (filled($columnsToAdd)) {
            Schema::table('ai_settings', function (Blueprint $table) use ($columnsToAdd) {
                foreach ($columnsToAdd as $col) {
                    $table->string($col)->nullable();
                }
            });
        }
    }
};
