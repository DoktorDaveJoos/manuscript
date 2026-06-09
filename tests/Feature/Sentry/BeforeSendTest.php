<?php

use App\Sentry\BeforeSend;
use Illuminate\Validation\ValidationException;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\ExceptionMechanism;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
|--------------------------------------------------------------------------
| BeforeSend filter
|--------------------------------------------------------------------------
|
| NativePHP rotates an internal secret on every app boot. A renderer window
| left open across a restart/auto-update — or an event dispatched before the
| `_php_native` security cookie is set — re-POSTs to the guarded
| `_native/api/*` bridge routes with a stale secret, and
| `PreventRegularBrowserAccess` answers `abort(403)`. That 403 escapes to
| Sentry's global exception handler tagged `handled: false`, so BeforeSend's
| "always send unhandled" branch force-reports it as if it were an app crash.
|
| These are expected framework noise, not crashes, and must never be sent.
|
*/

/**
 * Build a Sentry event that mirrors how an exception arrives in `before_send`.
 */
function sentryEvent(Throwable $exception, ?string $transaction, bool $handled): Event
{
    $event = Event::createEvent();

    if ($transaction !== null) {
        $event->setTransaction($transaction);
    }

    $event->setExceptions([
        new ExceptionDataBag(
            $exception,
            null,
            new ExceptionMechanism(ExceptionMechanism::TYPE_GENERIC, $handled),
        ),
    ]);

    return $event;
}

test('drops the benign NativePHP secret-rejection 403 from the events bridge route', function () {
    $event = sentryEvent(new HttpException(403), '/_native/api/events', handled: false);

    expect(BeforeSend::handle($event))->toBeNull();
});

test('drops benign rejections on the other _native/api bridge routes', function () {
    $event = sentryEvent(new HttpException(403), '/_native/api/booted', handled: false);

    expect(BeforeSend::handle($event))->toBeNull();
});

test('still reports a genuine unhandled crash on an app route', function () {
    $event = sentryEvent(new RuntimeException('boom'), '/books/1', handled: false);

    expect(BeforeSend::handle($event))->toBe($event);
});

test('still reports an unhandled HttpException raised outside the bridge routes', function () {
    $event = sentryEvent(new HttpException(403), '/books/1/export', handled: false);

    expect(BeforeSend::handle($event))->toBe($event);
});

/*
| A failed form-request validation throws ValidationException, which Laravel
| lists in `internalDontReport` and never logs. NativePHP's exception handler
| empties that list (Native\Desktop\Exceptions\Handler), so the re-registered
| Sentry `reportable` captures it as an unhandled `generic` crash even though
| the request is still answered with a 422 / redirect-back. It is ordinary
| user-input noise and must never be sent (Sentry 122860096).
*/

test('drops a failed form-request validation reported as an unhandled crash, regardless of route', function (string $field, string $route) {
    $exception = ValidationException::withMessages([$field => ["The {$field} field is required."]]);
    $event = sentryEvent($exception, $route, handled: false);

    expect(BeforeSend::handle($event))->toBeNull();
})->with([
    'title route' => ['title', '/books/1/chapters/1/title'],
    'content route' => ['content', '/books/9/chapters/4/content'],
]);
