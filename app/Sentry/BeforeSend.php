<?php

namespace App\Sentry;

use App\Models\AppSetting;
use Sentry\Event;

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
}
