<?php

namespace App\Http\Controllers;

use App\Enums\AiProvider;
use App\Models\AiSetting;
use App\Models\AppSetting;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        $settings = AppSetting::query()
            ->pluck('value', 'key')
            ->mapWithKeys(fn ($value, $key) => [$key => $value === 'true' ? true : ($value === 'false' ? false : $value)])
            ->all();

        $existing = AiSetting::query()->get()->keyBy(fn ($s) => $s->provider->value);

        $aiProviders = collect(AiProvider::cases())->map(function (AiProvider $provider) use ($existing) {
            $setting = $existing->get($provider->value) ?? AiSetting::forProvider($provider);

            return [
                ...$setting->toFrontendArray(),
                'label' => $provider->label(),
                'supports_embeddings' => $provider->supportsEmbeddings(),
            ];
        });

        // Fall back to the first book's writing style display if no global setting exists
        $writingStyleText = AppSetting::get('writing_style_text', '');
        if (! $writingStyleText) {
            $book = Book::query()->first();
            $writingStyleText = $book?->writing_style_display ?? '';
        }

        return Inertia::render('settings/index', [
            'settings' => $settings,
            'ai_providers' => $aiProviders,
            'writing_style_text' => $writingStyleText,
            'prose_pass_rules' => Book::globalProsePassRules(),
            'version' => config('app.version', '0.0.0'),
        ]);
    }

    public function updateWritingStyle(Request $request): JsonResponse
    {
        $request->validate([
            'writing_style_text' => ['required', 'string', 'max:5000'],
        ]);

        AppSetting::set('writing_style_text', $request->input('writing_style_text'));

        return response()->json(['message' => __('Writing style updated.')]);
    }

    public function updateProsePassRules(Request $request): JsonResponse
    {
        $request->validate([
            'rules' => ['required', 'array'],
            'rules.*.key' => ['required', 'string'],
            'rules.*.label' => ['required', 'string'],
            'rules.*.description' => ['required', 'string'],
            'rules.*.enabled' => ['required', 'boolean'],
        ]);

        AppSetting::set('prose_pass_rules', json_encode($request->input('rules')));

        return response()->json(['message' => __('Prose pass rules updated.')]);
    }
}
