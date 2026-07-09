<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppSettingsController extends Controller
{
    private const ALLOWED_KEYS = [
        'show_ai_features',
        'hide_formatting_toolbar',
        'typewriter_mode',
        'show_scenes',
        'show_status_bubbles',
        'show_word_count',
        'compact_word_count',
        'reranking_enabled',
        'cohere_api_key',
        'locale',
        'send_error_reports',
        'send_analytics',
        'crash_report_prompted',
        'language_prompted',
        'auto_update',
        'editor_font',
        'editor_font_size',
    ];

    public function appearance(): Response
    {
        $settings = AppSetting::query()
            ->pluck('value', 'key')
            ->mapWithKeys(fn ($value, $key) => [$key => $value === 'true' ? true : ($value === 'false' ? false : $value)])
            ->all();

        return Inertia::render('settings/appearance', [
            'settings' => $settings,
            'version' => config('app.version', '0.0.0'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'key' => ['required', 'string', 'in:'.implode(',', self::ALLOWED_KEYS)],
            'value' => ['required'],
        ]);

        $key = $request->input('key');
        $value = $request->input('value');

        AppSetting::set($key, $value);

        return response()->json(['message' => __('Setting updated.')]);
    }
}
