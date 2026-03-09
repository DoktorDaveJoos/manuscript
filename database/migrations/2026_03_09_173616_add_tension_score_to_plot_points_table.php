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
        Schema::table('plot_points', function (Blueprint $table) {
            $table->unsignedTinyInteger('tension_score')->nullable()->after('is_ai_derived');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plot_points', function (Blueprint $table) {
            $table->dropColumn('tension_score');
        });
    }
};
