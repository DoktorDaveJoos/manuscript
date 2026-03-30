<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editorial_reviews', function (Blueprint $table) {
            $table->boolean('is_pre_editorial')->default(false)->after('top_improvements');
        });
    }

    public function down(): void
    {
        Schema::table('editorial_reviews', function (Blueprint $table) {
            $table->dropColumn('is_pre_editorial');
        });
    }
};
