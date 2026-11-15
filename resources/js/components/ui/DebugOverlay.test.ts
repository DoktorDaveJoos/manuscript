import { describe, expect, it } from 'vitest';
import { isIgnoredBrowserError } from '@/components/ui/DebugOverlay';

describe('debug overlay browser error filtering', () => {
    it.each([
        'ResizeObserver loop completed with undelivered notifications.',
        'ResizeObserver loop limit exceeded',
    ])('ignores the browser resize observer warning: %s', (message) => {
        expect(isIgnoredBrowserError(message)).toBe(true);
    });

    it('continues reporting genuine JavaScript errors', () => {
        expect(isIgnoredBrowserError('ResizeObserver is not defined')).toBe(
            false,
        );
        expect(isIgnoredBrowserError('Something went wrong')).toBe(false);
    });
});
