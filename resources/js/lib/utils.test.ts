import { describe, expect, it } from 'vitest';
import {
    ensureSuccessfulResponse,
    formatCompactCount,
    formatWordCount,
} from './utils';

describe('word count formatting', () => {
    it('uses the requested locale for compact counts', () => {
        expect(formatCompactCount(1_250, 'en')).toBe('1.3k');
        expect(formatCompactCount(1_250, 'de')).toBe('1,3k');
    });

    it('uses the requested locale for full counts', () => {
        expect(formatWordCount(12_345, false, 'en')).toBe('12,345');
        expect(formatWordCount(12_345, false, 'de')).toBe('12.345');
    });
});

describe('successful response guard', () => {
    it('returns successful responses unchanged', async () => {
        const response = new Response(null, { status: 204 });

        await expect(
            ensureSuccessfulResponse(response, 'Fallback'),
        ).resolves.toBe(response);
    });

    it('uses the server error message for failed responses', async () => {
        const response = Response.json(
            { error: 'Cannot delete the last scene' },
            { status: 422 },
        );

        await expect(
            ensureSuccessfulResponse(response, 'Fallback'),
        ).rejects.toThrow('Cannot delete the last scene');
    });
});
