import { useCallback, useEffect, useRef } from 'react';

/**
 * Returns a debounced version of the callback that delays invocation
 * until `delay` ms have passed since the last call. Pending calls are
 * flushed on unmount so no edits are lost.
 */
export function useDebouncedCallback<T extends (...args: never[]) => void>(
    callback: T,
    delay: number,
): T {
    const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const callbackRef = useRef(callback);
    callbackRef.current = callback;

    const pendingArgsRef = useRef<Parameters<T> | null>(null);

    const debounced = useCallback(
        (...args: Parameters<T>) => {
            pendingArgsRef.current = args;
            if (timeoutRef.current) clearTimeout(timeoutRef.current);
            timeoutRef.current = setTimeout(() => {
                pendingArgsRef.current = null;
                callbackRef.current(...args);
            }, delay);
        },
        [delay],
    ) as T;

    // Flush on unmount so the last edit is always saved
    useEffect(() => {
        return () => {
            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
                if (pendingArgsRef.current) {
                    callbackRef.current(...pendingArgsRef.current);
                }
            }
        };
    }, []);

    return debounced;
}
