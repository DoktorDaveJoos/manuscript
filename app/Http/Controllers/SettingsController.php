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
            'proofreading_config' => Book::globalProofreadingConfig(),
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

    public function updateProofreadingConfig(Request $request): JsonResponse
    {
        $request->validate([
            'config' => ['required', 'array'],
            'config.spelling_enabled' => ['required', 'boolean'],
            'config.grammar_enabled' => ['required', 'boolean'],
            'config.grammar_checks' => ['required', 'array'],
        ]);

        AppSetting::set('proofreading_config', json_encode($request->input('config')));

        return response()->json(['message' => __('Proofreading settings updated.')]);
    }

    public function updateCustomDictionary(Request $request, Book $book): JsonResponse
    {
        $request->validate([
            'words' => ['required', 'array'],
            'words.*' => ['required', 'string', 'max:100'],
        ]);

        $book->update(['custom_dictionary' => $request->input('words')]);

        return response()->json(['message' => __('Custom dictionary updated.')]);
    }

    public function seedCustomDictionary(Book $book): JsonResponse
    {
        $names = collect();

        $book->characters()->get(['name', 'aliases'])->each(function ($char) use ($names) {
            $names->push($char->name);
            if ($char->aliases) {
                foreach ($char->aliases as $alias) {
                    $names->push($alias);
                }
            }
        });

        $book->wikiEntries()->get(['name'])->each(function ($entry) use ($names) {
            $names->push($entry->name);
        });

        $existing = $book->custom_dictionary ?? [];
        $merged = collect($existing)->merge($names)->unique()->sort()->values()->all();

        $book->update(['custom_dictionary' => $merged]);

        return response()->json([
            'message' => __('Dictionary seeded with :count entity names.', ['count' => count($merged) - count($existing)]),
            'words' => $merged,
        ]);
    }
}
