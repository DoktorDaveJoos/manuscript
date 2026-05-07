import type { Editor } from '@tiptap/react';
import { useCallback, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    commit as continueWritingCommit,
    stream as continueWritingStream,
} from '@/actions/App/Http/Controllers/ContinueWritingController';
import { useAiErrorToast } from '@/hooks/useAiErrorToast';
import { stripTags } from '@/lib/ruleCheckers';
import { escapeHtml, jsonFetchHeaders } from '@/lib/utils';
import type { ChapterVersion } from '@/types/models';

type StartArgs = {
    editor: Editor;
    bookId: number;
    chapterId: number;
    hint: string;
    wordGoal: number;
};

export type ContinueWritingReview = {
    bookId: number;
    chapterId: number;
    previous: ChapterVersion | null;
    new: ChapterVersion;
    addedWords: number;
};

function countWords(html: string | null | undefined): number {
    if (!html) return 0;
    return stripTags(html).match(/\S+/g)?.length ?? 0;
}

async function flushPaneByChapter(chapterId: number): Promise<void> {
    const el = document.querySelector(`[data-pane-chapter="${chapterId}"]`);
    if (!el) return;
    const flush = (el as unknown as Record<string, () => Promise<void>>)
        .__flushPane;
    if (typeof flush === 'function') {
        await flush();
    }
}

export function useContinueWriting() {
    const { t } = useTranslation('editor');
    const showAiErrorToast = useAiErrorToast();
    const [review, setReview] = useState<ContinueWritingReview | null>(null);
    const abortRef = useRef<AbortController | null>(null);
    const isWorkingRef = useRef(false);

    const cancel = useCallback(() => {
        abortRef.current?.abort();
        abortRef.current = null;
        isWorkingRef.current = false;
    }, []);

    const dismissReview = useCallback(() => setReview(null), []);

    const start = useCallback(
        async ({ editor, bookId, chapterId, hint, wordGoal }: StartArgs) => {
            if (isWorkingRef.current) return;

            isWorkingRef.current = true;
            setReview(null);
            const controller = new AbortController();
            abortRef.current = controller;

            const insertPos = editor.state.selection.$head.after();
            editor
                .chain()
                .focus()
                .insertContentAt(insertPos, '<p></p>')
                .setTextSelection(insertPos + 1)
                .run();

            // Coalesce SSE deltas onto rAF so we run one Tiptap transaction
            // per frame instead of one per token — token-rate inserts trigger
            // the autosave debounce on every event and wedge the editor.
            let pendingDelta = '';
            let flushScheduled = false;
            const flushDelta = () => {
                if (pendingDelta === '') return;
                const text = pendingDelta;
                pendingDelta = '';
                editor.chain().insertContent(escapeHtml(text)).run();
            };
            const scheduleFlush = () => {
                if (flushScheduled) return;
                flushScheduled = true;
                requestAnimationFrame(() => {
                    flushScheduled = false;
                    flushDelta();
                });
            };

            try {
                const response = await fetch(
                    continueWritingStream.url({
                        book: bookId,
                        chapter: chapterId,
                    }),
                    {
                        method: 'POST',
                        signal: controller.signal,
                        headers: {
                            ...jsonFetchHeaders(),
                            Accept: 'text/event-stream',
                        },
                        body: JSON.stringify({ hint, word_goal: wordGoal }),
                    },
                );

                if (!response.ok) {
                    throw new Error(
                        t('continueWriting.error.requestFailed', {
                            defaultValue: 'Continuation failed.',
                        }),
                    );
                }

                const reader = response.body?.getReader();
                if (!reader) {
                    throw new Error(
                        t('continueWriting.error.noStream', {
                            defaultValue: 'No stream available.',
                        }),
                    );
                }

                const decoder = new TextDecoder();
                let buffer = '';
                let streamErrored = false;

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

                        try {
                            const parsed = JSON.parse(payload);

                            if (parsed.error) {
                                streamErrored = true;
                                showAiErrorToast({
                                    kind:
                                        typeof parsed.kind === 'string'
                                            ? parsed.kind
                                            : 'unknown',
                                    message: parsed.error,
                                    provider: parsed.provider,
                                });
                                continue;
                            }

                            if (
                                typeof parsed.delta === 'string' &&
                                parsed.delta
                            ) {
                                pendingDelta += parsed.delta;
                                scheduleFlush();
                            }
                        } catch {
                            /* */
                        }
                    }
                }

                flushDelta();

                if (streamErrored) return;

                // Pane autosave must complete before commit — commit reads
                // scene rows, not the in-memory editor.
                await flushPaneByChapter(chapterId);

                const commitResponse = await fetch(
                    continueWritingCommit.url({
                        book: bookId,
                        chapter: chapterId,
                    }),
                    {
                        method: 'POST',
                        headers: jsonFetchHeaders(),
                    },
                );

                if (!commitResponse.ok) {
                    throw new Error(
                        t('continueWriting.error.commitFailed', {
                            defaultValue: 'Could not save the new version.',
                        }),
                    );
                }

                const payload = (await commitResponse.json()) as {
                    previous: ChapterVersion | null;
                    new: ChapterVersion;
                };

                const addedWords = Math.max(
                    0,
                    countWords(payload.new.content) -
                        countWords(payload.previous?.content),
                );

                setReview({
                    bookId,
                    chapterId,
                    previous: payload.previous,
                    new: payload.new,
                    addedWords,
                });
            } catch (e) {
                if ((e as Error).name === 'AbortError') return;
                showAiErrorToast({
                    kind: 'unknown',
                    message:
                        e instanceof Error
                            ? e.message
                            : t('continueWriting.error.requestFailed', {
                                  defaultValue: 'Continuation failed.',
                              }),
                });
            } finally {
                abortRef.current = null;
                isWorkingRef.current = false;
            }
        },
        [showAiErrorToast, t],
    );

    return { start, cancel, review, dismissReview };
}
