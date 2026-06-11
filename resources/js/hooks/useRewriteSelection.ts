import type { Editor } from '@tiptap/react';
import { useCallback, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    commit as rewriteSelectionCommit,
    stream as rewriteSelectionStream,
} from '@/actions/App/Http/Controllers/RewriteSelectionController';
import { useAiErrorToast } from '@/hooks/useAiErrorToast';
import { flushPaneByChapter } from '@/lib/pane';
import { proseMirrorBlockText } from '@/lib/proseText';
import { insertStreamedParagraphs } from '@/lib/streamedParagraphs';
import { jsonFetchHeaders } from '@/lib/utils';
import type { ChapterVersion } from '@/types/models';

type StartArgs = {
    editor: Editor;
    bookId: number;
    chapterId: number;
    hint: string;
    selection: { from: number; to: number };
};

export type RewriteSelectionReview = {
    kind: 'rewrite_selection';
    bookId: number;
    chapterId: number;
    previous: ChapterVersion | null;
    new: ChapterVersion;
};

// Too much surrounding context and the model paraphrases the surrounds
// instead of rewriting just the selection. The agent is told when an excerpt
// is truncated so it doesn't mistake the cut for the chapter boundary.
const SURROUND_WORD_CAP = 200;

function capWordsFromEnd(
    text: string,
    max: number,
): { text: string; truncated: boolean } {
    const words = text.match(/\S+/g);
    if (!words || words.length <= max) return { text, truncated: false };
    return { text: words.slice(-max).join(' '), truncated: true };
}

function capWordsFromStart(
    text: string,
    max: number,
): { text: string; truncated: boolean } {
    const words = text.match(/\S+/g);
    if (!words || words.length <= max) return { text, truncated: false };
    return { text: words.slice(0, max).join(' '), truncated: true };
}

function splitChapterAtSelection(
    editor: Editor,
    chapterId: number,
    range: { from: number; to: number },
): {
    before: string;
    selectionText: string;
    after: string;
    beforeTruncated: boolean;
    afterTruncated: boolean;
} {
    const { from, to } = range;
    const doc = editor.state.doc;
    const docEnd = doc.content.size;
    const head = doc.textBetween(0, from, '\n\n');
    const selectionText = doc.textBetween(from, to, '\n\n');
    const tail = doc.textBetween(to, docEnd, '\n\n');

    const pane = document.querySelector(`[data-pane-chapter="${chapterId}"]`);
    if (!pane) {
        const before = capWordsFromEnd(head, SURROUND_WORD_CAP);
        const after = capWordsFromStart(tail, SURROUND_WORD_CAP);
        return {
            before: before.text,
            selectionText,
            after: after.text,
            beforeTruncated: before.truncated,
            afterTruncated: after.truncated,
        };
    }

    const sceneEls = Array.from(
        pane.querySelectorAll<HTMLElement>('[id^="scene-"]'),
    );

    const beforeParts: string[] = [];
    const afterParts: string[] = [];
    let foundActive = false;

    for (const sceneEl of sceneEls) {
        if (sceneEl.contains(editor.view.dom)) {
            foundActive = true;
            if (head) beforeParts.push(head);
            if (tail) afterParts.push(tail);
            continue;
        }

        const pm = sceneEl.querySelector<HTMLElement>('.ProseMirror');
        const text = proseMirrorBlockText(pm);
        if (!text) continue;

        if (foundActive) {
            afterParts.push(text);
        } else {
            beforeParts.push(text);
        }
    }

    const before = capWordsFromEnd(beforeParts.join('\n\n'), SURROUND_WORD_CAP);
    const after = capWordsFromStart(afterParts.join('\n\n'), SURROUND_WORD_CAP);

    return {
        before: before.text,
        selectionText,
        after: after.text,
        beforeTruncated: before.truncated,
        afterTruncated: after.truncated,
    };
}

export function useRewriteSelection() {
    const { t } = useTranslation('editor');
    const showAiErrorToast = useAiErrorToast();
    const [review, setReview] = useState<RewriteSelectionReview | null>(null);
    const abortRef = useRef<AbortController | null>(null);
    const isWorkingRef = useRef(false);

    const cancel = useCallback(() => {
        abortRef.current?.abort();
        abortRef.current = null;
        isWorkingRef.current = false;
    }, []);

    const dismissReview = useCallback(() => setReview(null), []);

    const start = useCallback(
        async ({ editor, bookId, chapterId, hint, selection }: StartArgs) => {
            if (isWorkingRef.current) return;

            isWorkingRef.current = true;
            setReview(null);
            const controller = new AbortController();
            abortRef.current = controller;

            const {
                before,
                selectionText,
                after,
                beforeTruncated,
                afterTruncated,
            } = splitChapterAtSelection(editor, chapterId, selection);

            editor
                .chain()
                .focus()
                .setTextSelection(selection)
                .deleteSelection()
                .run();

            // Coalesce SSE deltas onto rAF so we run one Tiptap transaction
            // per frame instead of one per token.
            //
            // insertContent() parses strings as HTML, where newlines are mere
            // whitespace — newline runs must become real paragraph splits.
            // Trailing newlines are held back for the next flush, since the
            // paragraph break may continue in the next delta (the final flush
            // drops them instead).
            let pendingDelta = '';
            let flushScheduled = false;
            const flushDelta = (final = false) => {
                let text = pendingDelta;
                pendingDelta = '';
                if (final) {
                    text = text.replace(/\n+$/, '');
                } else {
                    const held = text.match(/\n+$/)?.[0];
                    if (held) {
                        text = text.slice(0, -held.length);
                        pendingDelta = held;
                    }
                }
                if (text === '') return;
                insertStreamedParagraphs(editor, text);
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
                    rewriteSelectionStream.url({
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
                        body: JSON.stringify({
                            selection: selectionText,
                            hint,
                            before,
                            after,
                            before_truncated: beforeTruncated,
                            after_truncated: afterTruncated,
                        }),
                    },
                );

                if (!response.ok) {
                    if (response.status === 409) {
                        showAiErrorToast({ kind: 'stale_version' });
                        return;
                    }
                    throw new Error(
                        t('rewriteSelection.error.requestFailed', {
                            defaultValue: 'Rewrite failed.',
                        }),
                    );
                }

                const reader = response.body?.getReader();
                if (!reader) {
                    throw new Error(
                        t('rewriteSelection.error.noStream', {
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

                flushDelta(true);

                if (streamErrored) return;

                // Pane autosave must complete before commit — commit reads
                // scene rows, not the in-memory editor.
                await flushPaneByChapter(chapterId);

                const commitResponse = await fetch(
                    rewriteSelectionCommit.url({
                        book: bookId,
                        chapter: chapterId,
                    }),
                    {
                        method: 'POST',
                        headers: jsonFetchHeaders(),
                    },
                );

                if (!commitResponse.ok) {
                    if (commitResponse.status === 409) {
                        showAiErrorToast({ kind: 'stale_version' });
                        return;
                    }
                    throw new Error(
                        t('rewriteSelection.error.commitFailed', {
                            defaultValue: 'Could not save the new version.',
                        }),
                    );
                }

                const payload = (await commitResponse.json()) as {
                    previous: ChapterVersion | null;
                    new: ChapterVersion;
                };

                setReview({
                    kind: 'rewrite_selection',
                    bookId,
                    chapterId,
                    previous: payload.previous,
                    new: payload.new,
                });
            } catch (e) {
                if ((e as Error).name === 'AbortError') return;
                showAiErrorToast({
                    kind: 'unknown',
                    message:
                        e instanceof Error
                            ? e.message
                            : t('rewriteSelection.error.requestFailed', {
                                  defaultValue: 'Rewrite failed.',
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
