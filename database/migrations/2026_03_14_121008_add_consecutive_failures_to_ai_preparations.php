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
        Schema::table('ai_preparations', function (Blueprint $table) {
            $table->unsignedInteger('consecutive_failures')->default(0)->after('phase_errors');
        });
    }

    public function down(): void
    {
        Schema::table('ai_preparations', function (Blueprint $table) {
            $table->dropColumn('consecutive_failures');
        });
    }
};
