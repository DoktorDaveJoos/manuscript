<?php

namespace App\Sentry;

use App\Models\AppSetting;
use ErrorException;
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

        if (self::isTransientCompiledViewRenameFailure($event)) {
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
     * The NativePHP Electron process runs `php artisan optimize` on boot
     * (vendor php.js), which compiles every Blade view via an atomic
     * write-then-rename. On Windows, antivirus real-time scanning — or any
     * other concurrent handle on the freshly written file — makes that
     * rename fail with "Access is denied", surfacing as an ErrorException
     * warning. The failure is self-healing: Electron logs it and keeps
     * booting, views compile on demand, and optimize re-runs on the next
     * launch. But the emptied `internalDontReport` (see
     * isExpectedValidationFailure) lets the warning reach Sentry as an
     * unhandled crash (Sentry 124580823). Drop it — it is never actionable.
     *
     * Scope: only `rename()` warnings whose paths sit in the compiled-views
     * directory (`storage/framework/views`). The Windows error text is
     * locale-dependent, so the message suffix is deliberately not matched.
     */
    private static function isTransientCompiledViewRenameFailure(Event $event): bool
    {
        foreach ($event->getExceptions() as $exception) {
            if (! is_a($exception->getType(), ErrorException::class, true)) {
                continue;
            }

            $message = $exception->getValue();

            if (str_starts_with($message, 'rename(')
                && preg_match('#[/\\\\]framework[/\\\\]views[/\\\\]#', $message) === 1) {
                return true;
            }
        }

        return false;
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
