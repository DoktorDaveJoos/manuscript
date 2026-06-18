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
| The chapter version actions (accept / accept-partial / reject / delete)
| each open with an `abort_if(..., 403, ...)` business-rule guard
| (ChapterController): a revision can only be accepted/rejected while it is
| still pending, and the current / last version cannot be deleted. These
| guards fire on benign, user-reachable races — classically the same chapter
| open in two editor panes, where resolving the pending AI revision in one
| pane leaves the other pane's stale Accept/Reject bar pointed at an
| already-accepted version. Accept flips the row's status to `accepted` (the
| row survives), so the late reject finds it present-but-non-pending and
| aborts 403 — the request is correctly refused and nothing is mutated. But
| the emptied `internalDontReport` (see isExpectedValidationFailure) lets that
| expected 403 reach Sentry as an unhandled `generic` crash (Sentry
| 126866018). The guard did its job; there is nothing to action.
*/
test('drops an expected non-pending / undeletable version-guard 403 reported as an unhandled crash', function (string $message, string $route) {
    $event = sentryEvent(new HttpException(403, $message), $route, handled: false);

    expect(BeforeSend::handle($event))->toBeNull();
})->with([
    'reject non-pending' => ['Only pending versions can be rejected.', '/books/10/chapters/34/versions/84/reject'],
    'accept non-pending' => ['Only pending versions can be accepted.', '/books/10/chapters/34/versions/84/accept'],
    'accept-partial non-pending' => ['Only pending versions can be accepted.', '/books/10/chapters/34/versions/84/accept-partial'],
    'delete current version' => ['Cannot delete the current version.', '/books/10/chapters/34/versions/84'],
    'delete last version' => ['Cannot delete the last version.', '/books/10/chapters/34/versions/84'],
]);

test('still reports a genuine, differently-messaged 403 on a version route', function () {
    $event = sentryEvent(
        new HttpException(403, 'Unexpected authorization failure.'),
        '/books/10/chapters/34/versions/84/reject',
        handled: false,
    );

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

/*
| On Windows, the NativePHP Electron process runs `php artisan optimize` on
| boot, which compiles every Blade view via an atomic write-then-rename.
| Antivirus real-time scanning (or any concurrent handle on the freshly
| written file) makes that rename fail with "Access is denied", surfacing as
| an ErrorException warning. The failure is self-healing — Electron logs it
| and keeps booting, views compile on demand, and optimize re-runs on the
| next launch — but the emptied `internalDontReport` lets it reach Sentry as
| an unhandled crash (Sentry 124580823). It is never actionable.
*/

test('drops a transient compiled-view rename failure reported as an unhandled crash', function (string $message) {
    $event = sentryEvent(new ErrorException($message), null, handled: false);

    expect(BeforeSend::handle($event))->toBeNull();
})->with([
    'windows boot optimize' => 'rename(C:\Users\Chris\AppData\Roaming\manuscript\storage\framework\views\a3b8B7E.tmp,C:\Users\Chris\AppData\Roaming\manuscript\storage\framework\views/a3bc8d6b48f2074ed09855bf1adf471d.php): Access is denied (code: 5)',
    'forward-slash paths' => 'rename(/app/storage/framework/views/a3b8B7E.tmp,/app/storage/framework/views/a3bc8d6b48f2074ed09855bf1adf471d.php): Access is denied (code: 5)',
]);

test('still reports a rename failure outside the compiled-views directory', function () {
    $exception = new ErrorException('rename(/tmp/a3b8B7E.tmp,/app/storage/app/books/1/export.epub): Permission denied');
    $event = sentryEvent($exception, null, handled: false);

    expect(BeforeSend::handle($event))->toBe($event);
});

test('still reports a non-rename ErrorException that mentions the compiled-views directory', function () {
    $exception = new ErrorException('file_put_contents(/app/storage/framework/views/a3b8B7E.tmp): Failed to open stream');
    $event = sentryEvent($exception, null, handled: false);

    expect(BeforeSend::handle($event))->toBe($event);
});
