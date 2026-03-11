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
        $locale = AppSetting::get('locale', 'en');

        if (in_array($locale, ['en', 'de'])) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
