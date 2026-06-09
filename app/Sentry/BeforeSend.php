<?php

namespace App\Sentry;

use App\Models\AppSetting;
use Illuminate\Validation\ValidationException;
use Sentry\Event;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Sentry `before_send` filter. Implemented as a static method so the
 * Laravel `config:cache` command (run during the NativePHP packaged build)
 * can serialize `config/sentry.php` — a Closure at this position breaks
 * serialization and halts the publish pipeline.
 */
class BeforeSend
{
    /**
     * Always send unhandled exceptions (app crashes) so we're never blind to
     * boot-time failures. For handled errors, respect the user's opt-in.
     */
    public static function handle(Event $event): ?Event
    {
        if (self::isBenignNativeBridgeRejection($event)) {
            return null;
        }

        if (self::isExpectedValidationFailure($event)) {
            return null;
        }

        foreach ($event->getExceptions() as $exception) {
            $mechanism = $exception->getMechanism();

            if ($mechanism !== null && $mechanism->isHandled() === false) {
                return $event;
            }
        }

        try {
            if (! AppSetting::get('send_error_reports', false)) {
                return null;
            }
        } catch (\Throwable) {
            // Can't read setting (DB down) = critical failure — send it.
            return $event;
        }

        return $event;
    }

    /**
     * NativePHP rotates its internal secret on every app boot. A renderer
     * window left open across a restart/auto-update — or an event dispatched
     * before the `_php_native` security cookie is set — re-POSTs to the
     * guarded `_native/api/*` bridge routes with a stale secret, and
     * `PreventRegularBrowserAccess` answers `abort(403)`. That 403 reaches
     * Sentry tagged `handled: false`, so the "always send unhandled" branch
     * above would force-report expected framework noise as if it were a crash.
     *
     * Scope the drop tightly: only HTTP-abort exceptions (403 et al.) on the
     * internal bridge routes. A genuine fault in those controllers surfaces as
     * a non-HttpException (TypeError, QueryException, …) and is still reported.
     */
    private static function isBenignNativeBridgeRejection(Event $event): bool
    {
        $transaction = $event->getTransaction();

        if ($transaction === null || ! str_contains($transaction, '_native/api')) {
            return false;
        }

        return self::hasExceptionOfType($event, HttpException::class);
    }

    /**
     * A failed form-request validation throws ValidationException. Laravel
     * itself lists it in `internalDontReport` and never logs it — the request
     * is still answered cleanly with a 422 / redirect-back. But NativePHP's
     * exception handler empties that ignore list
     * (Native\Desktop\Exceptions\Handler), so the re-registered Sentry
     * `reportable` (AppServiceProvider::configureSentry) captures it as an
     * unhandled `generic` crash and the "always send unhandled" branch above
     * force-reports ordinary user-input noise as if it were an app crash
     * (Sentry 122860096). Drop it unconditionally — it is never actionable.
     */
    private static function isExpectedValidationFailure(Event $event): bool
    {
        return self::hasExceptionOfType($event, ValidationException::class);
    }

    /**
     * Whether any exception attached to the event is, or extends, the given type.
     *
     * @param  class-string  $type
     */
    private static function hasExceptionOfType(Event $event, string $type): bool
    {
        foreach ($event->getExceptions() as $exception) {
            if (is_a($exception->getType(), $type, true)) {
                return true;
            }
        }

        return false;
    }
}
