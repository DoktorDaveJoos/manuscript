<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Book;
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
        'reranking_enabled',
        'cohere_api_key',
        'locale',
        'send_error_reports',
        'crash_report_prompted',
    ];

    public function appearance(): Response
    {
        $settings = AppSetting::query()
            ->pluck('value', 'key')
            ->mapWithKeys(fn ($value, $key) => [$key => $value === 'true' ? true : ($value === 'false' ? false : $value)])
            ->all();

        $book = Book::query()->select('id', 'title')->first();

        return Inertia::render('settings/appearance', [
            'settings' => $settings,
            'book' => $book?->only('id', 'title'),
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
