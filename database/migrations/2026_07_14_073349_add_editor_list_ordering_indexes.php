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
        Schema::table('storylines', function (Blueprint $table) {
            $table->index(['book_id', 'sort_order']);
        });

        Schema::table('chapters', function (Blueprint $table) {
            $table->index(['book_id', 'reader_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('storylines', function (Blueprint $table) {
            $table->dropIndex(['book_id', 'sort_order']);
        });

        Schema::table('chapters', function (Blueprint $table) {
            $table->dropIndex(['book_id', 'reader_order']);
        });
    }
};
