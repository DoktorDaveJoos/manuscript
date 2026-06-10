import type { Editor } from '@tiptap/react';
import { useCallback, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    commit as continueWritingCommit,
    stream as continueWritingStream,
} from '@/actions/App/Http/Controllers/ContinueWritingController';
import { useAiErrorToast } from '@/hooks/useAiErrorToast';
import { flushPaneByChapter } from '@/lib/pane';
import { stripTags } from '@/lib/ruleCheckers';
import { escapeHtml, jsonFetchHeaders } from '@/lib/utils';
import type { ChapterVersion } from '@/types/models';

type StartArgs = {
    editor: Editor;
    activeSceneId: number | null;
    bookId: number;
    chapterId: number;
    hint: string;
    wordGoal: number;
    chapterLink: 'auto' | 'continue' | 'fresh';
};

export type ContinueWritingReview = {
    kind: 'continue_writing';
    bookId: number;
    chapterId: number;
    previous: ChapterVersion | null;
    new: ChapterVersion;
    addedWords: number;
};

// Cap AFTER so the prompt stays focused on the insertion point. The agent is
// told when the excerpt is truncated so it doesn't mistake the cut for the
// end of the draft.
const AFTER_WORD_CAP = 250;

function countWords(html: string | null | undefined): number {
    if (!html) return 0;
    return stripTags(html).match(/\S+/g)?.length ?? 0;
}

function capWords(
    text: string,
    max: number,
): { text: string; truncated: boolean } {
    const words = text.match(/\S+/g);
    if (!words || words.length <= max) return { text, truncated: false };
    return { text: words.slice(0, max).join(' '), truncated: true };
}

// Root-level textContent glues block boundaries together ("…end.Start…").
// Join the top-level block nodes with blank lines instead so the prompt sees
// real paragraph breaks.
function proseMirrorBlockText(pm: HTMLElement | null): string {
    if (!pm) return '';
    return Array.from(pm.children)
        .map((el) => el.textContent?.trim() ?? '')
        .filter(Boolean)
        .join('\n\n');
}

// Recover the live editor instance for the active scene from its ProseMirror
// DOM node. Tiptap stashes `editor` on `view.dom`, so this returns the current
// (non-destroyed) editor even if the caller is holding a stale reference from
// before useChapterEditor recreated the instance.
function liveEditorFromDom(
    chapterId: number,
    sceneId: number | null,
): Editor | null {
    const selector =
        sceneId != null
            ? `[data-pane-chapter="${chapterId}"] #scene-${sceneId} .ProseMirror`
            : `[data-pane-chapter="${chapterId}"] .ProseMirror`;
    const pm = document.querySelector(selector) as
        | (HTMLElement & { editor?: Editor })
        | null;
    return pm?.editor ?? null;
}

// Walk every scene's rendered ProseMirror DOM (chapter pane is ChapterPane's
// data-pane-chapter root) and split the chapter's prose at the active editor's
// cursor.
//
// `before` includes every preceding scene's full text plus the active scene's
// text up to the cursor. `after` is ONLY the active scene's tail, capped to
// AFTER_WORD_CAP words. Scenes after the active one are intentionally omitted
// from the prompt: scene boundaries are structural, and including the next
// scene as "after-cursor" prose causes the model to bridge into it instead of
// continuing the user's current flow. Cross-scene structural context comes
// from the beats / preceding-chapter sections of the agent prompt instead.
function splitChapterAtCursor(
    editor: Editor,
    chapterId: number,
    activeSceneId: number | null,
): {
    before: string;
    after: string;
    afterTruncated: boolean;
    sceneFollows: boolean;
} {
    const activeSceneTail = () => {
        const pos = editor.state.selection.from;
        const end = editor.state.doc.content.size;
        return {
            head: editor.state.doc.textBetween(0, pos, '\n\n'),
            tail: editor.state.doc.textBetween(pos, end, '\n\n'),
        };
    };

    const pane = document.querySelector(`[data-pane-chapter="${chapterId}"]`);
    if (!pane || activeSceneId == null) {
        const { head, tail } = activeSceneTail();
        const capped = capWords(tail, AFTER_WORD_CAP);
        return {
            before: head,
            after: capped.text,
            afterTruncated: capped.truncated,
            sceneFollows: false,
        };
    }

    const sceneEls = Array.from(
        pane.querySelectorAll<HTMLElement>('[id^="scene-"]'),
    );

    const beforeParts: string[] = [];
    let afterText = '';
    let foundActive = false;
    let sceneFollows = false;

    for (const sceneEl of sceneEls) {
        if (sceneEl.id === `scene-${activeSceneId}`) {
            foundActive = true;
            const { head, tail } = activeSceneTail();
            if (head) beforeParts.push(head);
            afterText = tail;
            continue;
        }

        const pm = sceneEl.querySelector<HTMLElement>('.ProseMirror');
        const text = proseMirrorBlockText(pm);

        if (foundActive) {
            // Later scenes stay out of the prompt (scene boundaries are
            // structural), but the agent must know the chapter goes on.
            if (text) {
                sceneFollows = true;
                break;
            }
            continue;
        }

        if (text) beforeParts.push(text);
    }

    const capped = capWords(afterText.trim(), AFTER_WORD_CAP);

    return {
        before: beforeParts.join('\n\n'),
        after: capped.text,
        afterTruncated: capped.truncated,
        sceneFollows,
    };
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
        async ({
            editor,
            activeSceneId,
            bookId,
            chapterId,
            hint,
            wordGoal,
            chapterLink,
        }: StartArgs) => {
            if (isWorkingRef.current) return;

            // The caller-held activeEditor reference can be stale: tiptap's
            // useEditor destroys + recreates the instance when scene content
            // changes (e.g. cross-pane autosave sync), and the new instance
            // never re-fires `focus` while the dialog has focus. Recover the
            // live editor from the DOM (`view.dom.editor`) when needed.
            const liveEditor = editor.isDestroyed
                ? liveEditorFromDom(chapterId, activeSceneId)
                : editor;
            if (!liveEditor || liveEditor.isDestroyed) {
                showAiErrorToast({
                    kind: 'unknown',
                    message: t('continueWriting.error.editorUnavailable', {
                        defaultValue:
                            'Editor is not ready. Click into the scene and try again.',
                    }),
                });
                return;
            }

            isWorkingRef.current = true;
            setReview(null);
            const controller = new AbortController();
            abortRef.current = controller;

            const { before, after, afterTruncated, sceneFollows } =
                splitChapterAtCursor(liveEditor, chapterId, activeSceneId);
            const isInline = after.trim() !== '';

            if (isInline) {
                // Inline mode: stream straight at the cursor — no new paragraph.
                // Collapse any range selection to its start so the AI inserts
                // at the cursor instead of replacing the user's selection.
                liveEditor
                    .chain()
                    .focus()
                    .setTextSelection(liveEditor.state.selection.from)
                    .run();
            } else {
                // Append mode: open a fresh paragraph after the cursor block.
                const insertPos = liveEditor.state.selection.$head.after();
                liveEditor
                    .chain()
                    .focus()
                    .insertContentAt(insertPos, '<p></p>')
                    .setTextSelection(insertPos + 1)
                    .run();
            }

            // Coalesce SSE deltas onto rAF so we run one Tiptap transaction
            // per frame instead of one per token — token-rate inserts trigger
            // the autosave debounce on every event and wedge the editor.
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
                const chain = liveEditor.chain();
                text.split(/\n+/).forEach((paragraph, index) => {
                    if (index > 0) chain.splitBlock();
                    if (paragraph !== '') {
                        chain.insertContent(escapeHtml(paragraph));
                    }
                });
                chain.run();
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
                        body: JSON.stringify({
                            hint,
                            word_goal: wordGoal,
                            before,
                            after,
                            after_truncated: afterTruncated,
                            scene_follows: sceneFollows,
                            chapter_link: chapterLink,
                        }),
                    },
                );

                if (!response.ok) {
                    if (response.status === 409) {
                        showAiErrorToast({ kind: 'stale_version' });
                        return;
                    }
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

                flushDelta(true);

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
                    if (commitResponse.status === 409) {
                        showAiErrorToast({ kind: 'stale_version' });
                        return;
                    }
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
                    kind: 'continue_writing',
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
