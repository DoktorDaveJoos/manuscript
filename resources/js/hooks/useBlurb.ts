import { useCallback, useRef, useState } from 'react';
import { stream as blurbStream } from '@/actions/App/Http/Controllers/BlurbController';
import { jsonFetchHeaders } from '@/lib/utils';
import { useAiErrorToast } from './useAiErrorToast';

type BlurbStreamError = {
    error: string;
    kind?: string;
    provider?: string | null;
};

export async function readBlurbStream(
    body: ReadableStream<Uint8Array>,
    onText: (fullText: string) => void,
    onError: (error: BlurbStreamError) => void,
): Promise<string | null> {
    const reader = body.getReader();
    const decoder = new TextDecoder();
    let accumulated = '';
    let buffer = '';

    const processLine = (line: string): boolean => {
        if (!line.startsWith('data: ')) {
            return true;
        }

        const payload = line.slice(6);
        if (payload === '[DONE]') {
            return true;
        }

        let parsed: {
            delta?: string;
            error?: string;
            kind?: string;
            provider?: string | null;
        };

        try {
            parsed = JSON.parse(payload);
        } catch {
            return true;
        }

        if (parsed.error) {
            onError({
                error: parsed.error,
                kind: parsed.kind,
                provider: parsed.provider,
            });

            return false;
        }

        if (parsed.delta) {
            accumulated += parsed.delta;
            onText(accumulated);
        }

        return true;
    };

    while (true) {
        const { done, value } = await reader.read();
        if (done) {
            break;
        }

        buffer += decoder.decode(value, { stream: true });
        const lines = buffer.split('\n');
        buffer = lines.pop() ?? '';

        for (const line of lines) {
            if (!processLine(line)) {
                return null;
            }
        }
    }

    buffer += decoder.decode();
    if (buffer && !processLine(buffer)) {
        return null;
    }

    return accumulated;
}

/**
 * Streams an AI-generated back-cover blurb (German UI: "Klappentext") for a book and
 * reports the accumulated text back on every delta, so the caller can render it live
 * into a textarea. Mirrors the SSE protocol used by Continue Writing: `data: {delta}`
 * lines terminated by `data: [DONE]`, with classified `{error, kind, provider}` payloads.
 */
export function useBlurb() {
    const showAiErrorToast = useAiErrorToast();
    const [isGenerating, setIsGenerating] = useState(false);
    const controllerRef = useRef<AbortController | null>(null);

    const generate = useCallback(
        async (
            bookId: number,
            onText: (fullText: string) => void,
        ): Promise<string | null> => {
            controllerRef.current?.abort();
            const controller = new AbortController();
            controllerRef.current = controller;
            setIsGenerating(true);

            try {
                const response = await fetch(
                    blurbStream.url({ book: bookId }),
                    {
                        method: 'POST',
                        signal: controller.signal,
                        headers: {
                            ...jsonFetchHeaders(),
                            Accept: 'text/event-stream',
                        },
                    },
                );

                if (!response.ok || !response.body) {
                    showAiErrorToast({ kind: 'unknown' });
                    return null;
                }

                return await readBlurbStream(response.body, onText, (error) => {
                    showAiErrorToast({
                        kind: error.kind ?? 'unknown',
                        message: error.error,
                        provider: error.provider,
                    });
                });
            } catch (e) {
                if (e instanceof DOMException && e.name === 'AbortError') {
                    return null;
                }
                showAiErrorToast({
                    kind: 'unknown',
                    message: e instanceof Error ? e.message : undefined,
                });

                return null;
            } finally {
                if (controllerRef.current === controller) {
                    setIsGenerating(false);
                    controllerRef.current = null;
                }
            }
        },
        [showAiErrorToast],
    );

    const cancel = useCallback(() => {
        controllerRef.current?.abort();
        setIsGenerating(false);
    }, []);

    return { generate, isGenerating, cancel };
}
