<?php

namespace App\Http\Middleware;

use App\Models\AiSetting;
use App\Models\AppSetting;
use App\Models\Book;
use App\Models\License;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Laravel\Ai\Ai;
use Throwable;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // SetLocale middleware runs before Inertia and has already applied
        // the saved locale, so app()->getLocale() is authoritative here
        // (no second AppSetting query needed for locale).
        $locale = app()->getLocale();

        try {
            // Pre-load all the AppSetting keys this middleware needs in one
            // query so the closures below read from the per-request cache
            // instead of issuing N separate SELECTs.
            AppSetting::warmCache([
                'show_ai_features',
                'hide_formatting_toolbar',
                'typewriter_mode',
                'show_scenes',
                'send_error_reports',
                'crash_report_prompted',
                'language_prompted',
                'editor_font',
                'editor_font_size',
            ]);
        } catch (Throwable $e) {
            report($e);

            // Database is probably unavailable (failed migration, corrupt file).
            // Return minimal props so the frontend can render a recovery page
            // instead of a raw 500.
            return [
                ...parent::share($request),
                'name' => config('app.name'),
                'app_version' => config('app.version', '0.0.0'),
                'boot_error' => true,
            ];
        }

        // If the connector repaired a corrupt database on this boot, pass
        // the recovery details so the frontend can show a one-time notification.
        // The dialog is dismissed via React state in app.tsx (it lives above the
        // Inertia router and is never unmounted during SPA navigation).
        $repairInfo = app()->bound('database.repaired')
            ? app()->make('database.repaired')
            : null;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'app_version' => config('app.version', '0.0.0'),
            ...($repairInfo ? [
                'database_repaired' => true,
                'repair_details' => [
                    'recovered' => $repairInfo['recovered'] ?? [],
                    'failed' => $repairInfo['failed'] ?? [],
                ],
            ] : []),
            'auth' => [
                'user' => $request->user(),
            ],
            'license' => function () {
                $license = License::active();

                return [
                    'active' => $license !== null,
                    'masked_key' => $license?->license_key
                        ? substr($license->license_key, 0, 8).'••••••••'
                        : null,
                ];
            },
            'books_list' => fn () => Book::query()->select('id', 'title')->get(),
            'app_settings' => fn () => [
                'show_ai_features' => AppSetting::get('show_ai_features', true),
                'hide_formatting_toolbar' => AppSetting::get('hide_formatting_toolbar', false),
                'typewriter_mode' => AppSetting::get('typewriter_mode', false),
                'show_scenes' => AppSetting::get('show_scenes', true),
                'send_error_reports' => AppSetting::get('send_error_reports', false),
                'crash_report_prompted' => AppSetting::get('crash_report_prompted', false),
                'language_prompted' => AppSetting::get('language_prompted', false),
                'locale' => $locale,
                'editor_font' => AppSetting::get('editor_font', 'eb-garamond'),
                'editor_font_size' => (int) AppSetting::get('editor_font_size', 18),
            ],
            'ai_configured' => fn () => $this->activeAiSetting()?->isConfigured() ?? false,
            'ai_key_recovery_needed' => fn () => (bool) $this->activeAiSetting()?->api_key_recovery_needed,
            'ai_provider_label' => fn () => $this->activeAiSetting()?->provider->label(),
            'ai_default_model' => fn () => $this->activeDefaultTextModel(),
            'sidebar_storylines' => function () use ($request) {
                $book = $request->route('book');
                if (! $book instanceof Book) {
                    return null;
                }

                // Reuse if the controller already loaded storylines.chapters
                if ($book->relationLoaded('storylines')) {
                    $storylines = $book->storylines;
                    if ($storylines->isEmpty() || $storylines->first()->relationLoaded('chapters')) {
                        return $storylines;
                    }
                }

                // Otherwise load the minimal set the sidebar needs
                $book->load([
                    'storylines' => fn ($q) => $q->orderBy('sort_order'),
                    'storylines.chapters' => fn ($q) => $q
                        ->select('id', 'book_id', 'storyline_id', 'title', 'reader_order', 'status', 'word_count')
                        ->orderBy('reader_order'),
                ]);

                return $book->storylines;
            },
        ];
    }

    /**
     * Resolve the active AI provider once per request. Three shared props read
     * different fields off the same row — without memoization each Inertia
     * render fires three separate `activeProvider()` queries.
     */
    private function activeAiSetting(): ?AiSetting
    {
        return once(fn () => AiSetting::activeProvider());
    }

    /**
     * Resolve the default chat model the active provider would use for a
     * non-trivial coach turn. Returns null if no provider is configured or if
     * the SDK can't resolve one (e.g. provider isn't registered in ai.php).
     */
    private function activeDefaultTextModel(): ?string
    {
        $setting = $this->activeAiSetting();

        if ($setting === null || ! $setting->isConfigured()) {
            return null;
        }

        try {
            return Ai::textProvider($setting->provider->value)->defaultTextModel();
        } catch (Throwable) {
            return null;
        }
    }
}
