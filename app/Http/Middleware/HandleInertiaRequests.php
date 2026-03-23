<?php

namespace App\Http\Middleware;

use App\Models\AiSetting;
use App\Models\AppSetting;
use App\Models\Book;
use App\Models\License;
use App\Services\FreeTierLimits;
use Illuminate\Http\Request;
use Inertia\Middleware;

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
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'app_version' => config('app.version', '0.0.0'),
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
            ],
            'ai_configured' => fn () => AiSetting::activeProvider()?->isConfigured() ?? false,
            'locale' => fn () => app()->getLocale(),
            'free_tier' => function () use ($request) {
                if (License::isActive()) {
                    return null;
                }

                $book = $request->route('book');
                $bookInstance = $book instanceof Book ? $book : null;

                return [
                    'books' => [
                        'count' => FreeTierLimits::bookCount(),
                        'limit' => FreeTierLimits::MAX_BOOKS,
                    ],
                    'storylines' => $bookInstance ? [
                        'count' => FreeTierLimits::storylineCount($bookInstance),
                        'limit' => FreeTierLimits::MAX_STORYLINES,
                    ] : null,
                    'wiki_entries' => $bookInstance ? [
                        'count' => FreeTierLimits::wikiEntryCount($bookInstance),
                        'limit' => FreeTierLimits::MAX_WIKI_ENTRIES,
                    ] : null,
                    'export_free_formats' => FreeTierLimits::FREE_EXPORT_FORMATS,
                ];
            },
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
}
