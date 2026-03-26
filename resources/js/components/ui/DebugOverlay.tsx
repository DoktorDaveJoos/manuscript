import { Component, useCallback, useEffect, useState } from 'react';
import type { ErrorInfo, ReactNode } from 'react';
import { Bug, Check, Copy, RefreshCw, X } from 'lucide-react';

type CapturedError = {
    message: string;
    stack: string;
    url: string;
    type: 'react' | 'js' | 'promise';
};

const MAX_ERRORS = 50;

let pushError: ((error: CapturedError) => void) | null = null;

function captureError(
    message: string,
    stack: string,
    type: CapturedError['type'],
) {
    pushError?.({
        message,
        stack,
        url: window.location.pathname,
        type,
    });
}

function formatForClipboard(errors: CapturedError[]): string {
    return errors
        .map(
            (e) =>
                `URL: ${e.url}\nType: ${e.type}\nError: ${e.message}\n\nStack:\n${e.stack}`,
        )
        .join('\n\n---\n\n');
}

function DebugOverlayUI({
    errors,
    onClose,
    hasReactCrash,
}: {
    errors: CapturedError[];
    onClose: () => void;
    hasReactCrash: boolean;
}) {
    const [copied, setCopied] = useState(false);

    const handleCopy = useCallback(() => {
        navigator.clipboard
            .writeText(formatForClipboard(errors))
            .then(() => {
                setCopied(true);
                setTimeout(() => setCopied(false), 2000);
            })
            .catch(() => {});
    }, [errors]);

    if (errors.length === 0) return null;

    return (
        <div className="fixed inset-0 z-[9999] flex items-end justify-center p-6">
            <div className="fixed inset-0 bg-ink/20" onClick={onClose} />
            <div className="relative flex max-h-[70vh] w-full max-w-[640px] flex-col overflow-hidden rounded-xl border border-delete/20 bg-surface-card shadow-lg">
                <div className="flex shrink-0 items-center justify-between border-b border-border-light px-4 py-3">
                    <div className="flex items-center gap-2">
                        <Bug size={14} className="text-delete" />
                        <span className="text-[13px] font-medium text-ink">
                            {errors.length === 1
                                ? '1 error caught'
                                : `${errors.length} errors caught`}
                        </span>
                    </div>
                    <div className="flex items-center gap-1">
                        {hasReactCrash && (
                            <button
                                type="button"
                                onClick={() => window.location.reload()}
                                className="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium text-ink-muted transition-colors hover:bg-neutral-bg hover:text-ink"
                            >
                                <RefreshCw size={12} />
                                Reload page
                            </button>
                        )}
                        <button
                            type="button"
                            onClick={handleCopy}
                            className="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium text-ink-muted transition-colors hover:bg-neutral-bg hover:text-ink"
                        >
                            {copied ? (
                                <Check size={12} />
                            ) : (
                                <Copy size={12} />
                            )}
                            {copied ? 'Copied' : 'Copy all'}
                        </button>
                        <button
                            type="button"
                            onClick={onClose}
                            className="flex items-center justify-center rounded-md p-1.5 text-ink-faint transition-colors hover:bg-neutral-bg hover:text-ink"
                        >
                            <X size={14} />
                        </button>
                    </div>
                </div>

                <div className="flex-1 overflow-y-auto overscroll-contain">
                    {errors.map((error, i) => (
                        <div
                            key={i}
                            className={`flex flex-col gap-2 px-4 py-3 ${i < errors.length - 1 ? 'border-b border-border-subtle' : ''}`}
                        >
                            <div className="flex items-start justify-between gap-3">
                                <p className="text-[13px] font-medium text-delete">
                                    {error.message}
                                </p>
                                <span className="shrink-0 rounded-full bg-neutral-bg px-2 py-0.5 text-[11px] font-medium text-ink-faint">
                                    {error.type}
                                </span>
                            </div>
                            <p className="text-[11px] text-ink-faint">
                                {error.url}
                            </p>
                            {error.stack && (
                                <pre className="max-h-[200px] overflow-auto rounded-lg bg-neutral-bg p-3 font-mono text-[11px] leading-4 text-ink-muted">
                                    {error.stack}
                                </pre>
                            )}
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

function DebugOverlayInner({ children }: { children: ReactNode }) {
    const [errors, setErrors] = useState<CapturedError[]>([]);

    useEffect(() => {
        pushError = (error) => {
            setErrors((prev) => {
                if (prev[0]?.message === error.message && prev[0]?.type === error.type) {
                    return prev;
                }
                return [error, ...prev].slice(0, MAX_ERRORS);
            });
        };

        const handleError = (event: ErrorEvent) => {
            captureError(
                event.message || 'Unknown error',
                event.error?.stack ||
                    `at ${event.filename}:${event.lineno}:${event.colno}`,
                'js',
            );
        };

        const handleRejection = (event: PromiseRejectionEvent) => {
            const reason = event.reason;
            const message =
                reason instanceof Error
                    ? reason.message
                    : String(reason || 'Unhandled promise rejection');
            const stack =
                reason instanceof Error ? reason.stack || '' : '';
            captureError(message, stack, 'promise');
        };

        window.addEventListener('error', handleError);
        window.addEventListener('unhandledrejection', handleRejection);

        return () => {
            pushError = null;
            window.removeEventListener('error', handleError);
            window.removeEventListener('unhandledrejection', handleRejection);
        };
    }, []);

    const handleClose = useCallback(() => {
        setErrors([]);
    }, []);

    const hasReactCrash = errors.some((e) => e.type === 'react');

    return (
        <>
            {children}
            {errors.length > 0 && (
                <DebugOverlayUI
                    errors={errors}
                    onClose={handleClose}
                    hasReactCrash={hasReactCrash}
                />
            )}
        </>
    );
}

type ErrorBoundaryProps = { children: ReactNode };
type ErrorBoundaryState = { hasError: boolean };

class DebugErrorBoundary extends Component<
    ErrorBoundaryProps,
    ErrorBoundaryState
> {
    state: ErrorBoundaryState = { hasError: false };

    static getDerivedStateFromError(): ErrorBoundaryState {
        return { hasError: true };
    }

    componentDidCatch(error: Error, info: ErrorInfo) {
        const componentStack = info.componentStack || '';
        captureError(
            error.message,
            (error.stack || '') + '\n\nComponent stack:' + componentStack,
            'react',
        );
    }

    render() {
        if (this.state.hasError) {
            return (
                <div className="flex h-screen items-center justify-center bg-surface">
                    <div className="flex flex-col items-center gap-3">
                        <p className="text-sm text-ink-muted">
                            Something went wrong. Check the error overlay
                            for details.
                        </p>
                    </div>
                </div>
            );
        }
        return this.props.children;
    }
}

export default function DebugOverlay({
    children,
}: {
    children: ReactNode;
}) {
    return (
        <DebugOverlayInner>
            <DebugErrorBoundary>{children}</DebugErrorBoundary>
        </DebugOverlayInner>
    );
}
