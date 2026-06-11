<?php

namespace App\Http\Controllers;

use App\Enums\AiProvider;
use App\Models\AiSetting;
use App\Models\AppSetting;
use App\Models\Book;
use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(private BackupService $backups) {}

    public function index(): Response
    {
        $settings = AppSetting::query()
            ->pluck('value', 'key')
            ->mapWithKeys(fn ($value, $key) => [$key => $value === 'true' ? true : ($value === 'false' ? false : $value)])
            ->all();

        $existing = AiSetting::query()->get()->keyBy(fn ($s) => $s->provider->value);

        $aiProviders = collect(AiProvider::userFacing())->map(function (AiProvider $provider) use ($existing) {
            $setting = $existing->get($provider->value) ?? AiSetting::forProvider($provider);

            return [
                ...$setting->toFrontendArray(),
                'label' => $provider->label(),
            ];
        });

        return Inertia::render('settings/index', [
            'settings' => $settings,
            'ai_providers' => $aiProviders,
            'proofreading_config' => Book::globalProofreadingConfig(),
            'version' => config('app.version', '0.0.0'),
            'backup' => [
                'has_rollback' => $this->backups->state()['has_rollback'],
                'last_export_at' => AppSetting::get(BackupService::LAST_EXPORT_AT_KEY),
            ],
        ]);
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
