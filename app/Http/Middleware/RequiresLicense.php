<?php

namespace App\Http\Middleware;

use App\Models\License;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequiresLicense
{
    /**
     * Routes that work without an active license.
     *
     * Health/loading endpoints, the welcome page itself, license activation,
     * and update endpoints (so users can fetch fixes even before activating).
     */
    private const EXEMPT_ROUTES = [
        'loading',
        'ready',
        'repair-status',
        'license.welcome',
        'license.activate',
        'license.deactivate',
        'license.revalidate',
        'update.check',
        'update.download',
        'update.install',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (License::isActive() || $request->routeIs(self::EXEMPT_ROUTES)) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => __('This app requires an active Manuscript license.'),
            ], 403);
        }

        return redirect()->route('license.welcome');
    }
}
