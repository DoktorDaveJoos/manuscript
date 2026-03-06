<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storylines', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('chapters', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('scenes', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('storylines', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('chapters', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('scenes', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
