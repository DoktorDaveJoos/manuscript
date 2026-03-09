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
        Schema::table('books', function (Blueprint $table) {
            $table->unsignedBigInteger('ai_input_tokens')->default(0);
            $table->unsignedBigInteger('ai_output_tokens')->default(0);
            $table->unsignedBigInteger('ai_cost_microdollars')->default(0);
            $table->dateTime('ai_usage_reset_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['ai_input_tokens', 'ai_output_tokens', 'ai_cost_microdollars', 'ai_usage_reset_at']);
        });
    }
};
