import { useCallback, useRef, useState } from 'react';
import { stream as blurbStream } from '@/actions/App/Http/Controllers/BlurbController';
import { jsonFetchHeaders } from '@/lib/utils';
import { useAiErrorToast } from './useAiErrorToast';

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
        async (bookId: number, onText: (fullText: string) => void) => {
            controllerRef.current?.abort();
            const controller = new AbortController();
            controllerRef.current = controller;
            setIsGenerating(true);

            let accumulated = '';

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
                    return;
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\n');
                    buffer = lines.pop() ?? '';

                    for (const line of lines) {
                        if (!line.startsWith('data: ')) continue;
                        const payload = line.slice(6);
                        if (payload === '[DONE]') continue;

                        let parsed: {
                            delta?: string;
                            error?: string;
                            kind?: string;
                            provider?: string | null;
                        };
                        try {
                            parsed = JSON.parse(payload);
                        } catch {
                            continue;
                        }

                        if (parsed.error) {
                            showAiErrorToast({
                                kind: parsed.kind ?? 'unknown',
                                message: parsed.error,
                                provider: parsed.provider,
                            });
                            return;
                        }

                        if (parsed.delta) {
                            accumulated += parsed.delta;
                            onText(accumulated);
                        }
                    }
                }
            } catch (e) {
                if (e instanceof DOMException && e.name === 'AbortError') {
                    return;
                }
                showAiErrorToast({
                    kind: 'unknown',
                    message: e instanceof Error ? e.message : undefined,
                });
            } finally {
                setIsGenerating(false);
                controllerRef.current = null;
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
