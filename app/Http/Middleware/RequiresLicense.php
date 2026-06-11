<?php

namespace App\Http\Middleware;

use App\Models\License;
use App\Support\Trial;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequiresLicense
{
    /**
     * Health/loading endpoints, the welcome page itself, license activation,
     * trial start, and update endpoints (so users can fetch fixes even before
     * activating).
     */
    private const EXEMPT_ROUTES = [
        'loading',
        'ready',
        'repair-status',
        'license.welcome',
        'license.activate',
        'license.deactivate',
        'license.revalidate',
        'trial.start',
        'update.check',
        'update.download',
        'update.install',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (License::isActive() || Trial::isActive() || $request->routeIs(self::EXEMPT_ROUTES)) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->inertia()) {
            return response()->json([
                'message' => __('This app requires an active Manuscript license.'),
            ], 403);
        }

        return redirect()->route('license.welcome');
    }
}
