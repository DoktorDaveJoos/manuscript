import { AlertTriangle } from 'lucide-react';

/**
 * Standalone error screen rendered when the database cannot be loaded.
 * Has zero Inertia/DB dependencies — locale is unavailable, so English only.
 */
export default function BootErrorScreen() {
    return (
        <div className="flex min-h-screen items-center justify-center bg-surface">
            <div className="flex max-w-sm flex-col items-center gap-6 text-center">
                <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-neutral-bg">
                    <AlertTriangle className="size-6 text-ink-muted" />
                </div>

                <div className="flex flex-col gap-2">
                    <h1 className="text-xl font-semibold text-ink">
                        Something went wrong
                    </h1>
                    <p className="text-sm leading-relaxed text-ink-muted">
                        The database could not be loaded. Please restart the
                        application. If the problem persists, your data may need
                        to be reset.
                    </p>
                </div>

                <button
                    type="button"
                    onClick={() => window.location.replace('/')}
                    className="flex h-11 items-center justify-center rounded-md bg-ink px-6 text-sm font-semibold text-surface"
                >
                    Try Again
                </button>
            </div>
        </div>
    );
}
