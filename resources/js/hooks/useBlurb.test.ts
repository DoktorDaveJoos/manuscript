import { describe, expect, it, vi } from 'vitest';
import { readBlurbStream } from './useBlurb';

function streamChunks(chunks: string[]): ReadableStream<Uint8Array> {
    const encoder = new TextEncoder();

    return new ReadableStream({
        start(controller) {
            for (const chunk of chunks) {
                controller.enqueue(encoder.encode(chunk));
            }
            controller.close();
        },
    });
}

describe('readBlurbStream', () => {
    it('returns the final accumulated text after streaming split deltas', async () => {
        const updates: string[] = [];
        const onError = vi.fn();
        const stream = streamChunks([
            'data: {"delta":"A dark"}\n',
            'data: {"delta":" secret"}\n',
            'data: [DONE]\n',
        ]);

        await expect(
            readBlurbStream(stream, (text) => updates.push(text), onError),
        ).resolves.toBe('A dark secret');
        expect(updates).toEqual(['A dark', 'A dark secret']);
        expect(onError).not.toHaveBeenCalled();
    });

    it('returns null instead of treating a partial response as complete on error', async () => {
        const onError = vi.fn();
        const stream = streamChunks([
            'data: {"delta":"Partial"}\n',
            'data: {"error":"Provider failed","kind":"provider"}\n',
        ]);

        await expect(
            readBlurbStream(stream, vi.fn(), onError),
        ).resolves.toBeNull();
        expect(onError).toHaveBeenCalledWith({
            error: 'Provider failed',
            kind: 'provider',
        });
    });
});
