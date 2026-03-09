<?php

namespace App\Http\Middleware;

use App\Models\AiSetting;
use App\Models\AppSetting;
use App\Models\Book;
use App\Models\License;
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
            'auth' => [
                'user' => $request->user(),
            ],
            'license' => function () {
                $license = License::active();

                return [
                    'active' => $license !== null,
                    'masked_key' => $license?->key
                        ? 'MANU.'.substr(explode('.', $license->key, 3)[1] ?? '', 0, 4).'••••.••••••'.substr($license->key, -2)
                        : null,
                ];
            },
            'books_list' => fn () => Book::query()->select('id', 'title')->get(),
            'app_settings' => fn () => [
                'show_ai_features' => AppSetting::get('show_ai_features', true),
                'hide_formatting_toolbar' => AppSetting::get('hide_formatting_toolbar', false),
                'typewriter_mode' => AppSetting::get('typewriter_mode', false),
                'show_scenes' => AppSetting::get('show_scenes', true),
            ],
            'ai_configured' => fn () => AiSetting::activeProvider()?->isConfigured() ?? false,
        ];
    }
}
