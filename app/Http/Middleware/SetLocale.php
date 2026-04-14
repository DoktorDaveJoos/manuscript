<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $locale = AppSetting::get('locale', 'en');
        } catch (\Throwable) {
            $locale = config('app.locale', 'en');
        }

        if (in_array($locale, ['en', 'de', 'es'])) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
