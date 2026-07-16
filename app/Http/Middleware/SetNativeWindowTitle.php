<?php

namespace App\Http\Middleware;

use App\Models\Book;
use Closure;
use Illuminate\Http\Request;
use Inertia\Support\Header;
use Native\Desktop\Facades\Window;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SetNativeWindowTitle
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldUpdateTitle($request, $response)) {
            return $response;
        }

        $book = $request->route('book');
        $appName = (string) config('app.name', 'Manuscript');
        $title = $book instanceof Book
            ? "{$book->title} — {$appName}"
            : $appName;

        try {
            Window::get('main')->title($title);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $response;
    }

    private function shouldUpdateTitle(Request $request, Response $response): bool
    {
        if (! config('nativephp-internal.running') || ! $request->isMethod('GET') || ! $response->isSuccessful()) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type');

        return str_contains($contentType, 'text/html')
            || $response->headers->has(Header::INERTIA);
    }
}
