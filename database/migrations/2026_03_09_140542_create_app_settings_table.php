<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        DB::table('app_settings')->insert([
            ['key' => 'show_ai_features', 'value' => 'true', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'hide_formatting_toolbar', 'value' => 'false', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'typewriter_mode', 'value' => 'false', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'show_scenes', 'value' => 'true', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
