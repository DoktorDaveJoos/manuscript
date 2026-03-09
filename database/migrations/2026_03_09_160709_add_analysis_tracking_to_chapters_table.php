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
            $table->string('analysis_status')->nullable()->after('hook_type');
            $table->text('analysis_error')->nullable()->after('analysis_status');
            $table->timestamp('analyzed_at')->nullable()->after('analysis_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            $table->dropColumn(['analysis_status', 'analysis_error', 'analyzed_at']);
        });
    }
};
