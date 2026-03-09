<?php

use App\Enums\AiProvider;
use App\Models\AiSetting;
use App\Models\AppSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate data: if any book had AI enabled, ensure show_ai_features is on
        $hasAiEnabled = DB::table('books')->where('ai_enabled', true)->exists();
        if ($hasAiEnabled) {
            AppSetting::set('show_ai_features', true);
        }

        // If a book has a specific provider, ensure that provider's AiSetting is enabled
        $firstProvider = DB::table('books')
            ->where('ai_enabled', true)
            ->whereNotNull('ai_provider')
            ->value('ai_provider');

        if ($firstProvider) {
            $provider = AiProvider::tryFrom($firstProvider);
            if ($provider) {
                AiSetting::selectProvider($provider);
            }
        }

        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['ai_enabled', 'ai_provider', 'ai_model']);
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->boolean('ai_enabled')->default(false);
            $table->string('ai_provider')->nullable();
            $table->string('ai_model')->nullable();
        });
    }
};
