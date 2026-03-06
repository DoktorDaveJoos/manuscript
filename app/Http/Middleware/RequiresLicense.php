<?php

namespace App\Http\Middleware;

use App\Models\License;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequiresLicense
{
    public function handle(Request $request, Closure $next): Response
    {
        if (License::isActive()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'This feature requires an active Manuscript licence.',
            ], 403);
        }

        return redirect()->route('ai-settings.index');
    }
}
