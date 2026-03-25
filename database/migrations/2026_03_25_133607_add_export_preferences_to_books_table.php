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
            $table->string('export_template')->nullable();
            $table->string('export_font_pairing')->nullable();
            $table->string('export_scene_break_style')->nullable();
            $table->boolean('export_drop_caps')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn([
                'export_template', 'export_font_pairing',
                'export_scene_break_style', 'export_drop_caps',
            ]);
        });
    }
};
