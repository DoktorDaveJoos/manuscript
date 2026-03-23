import type { Editor } from '@tiptap/core';
import { ChevronDown, ChevronUp, Replace, Search, X } from 'lucide-react';
import type { RefObject } from 'react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import ToggleButton from '@/components/ui/ToggleButton';
import {
    buildPattern,
    updateSearchHighlight,
} from '@/extensions/SearchHighlightExtension';
import type { Scene } from '@/types/models';

type ChapterMatch = {
    sceneId: number;
    from: number;
    to: number;
};

function collectMatches(
    scenes: Scene[],
    registry: Map<number, Editor>,
    params: {
        query: string;
        caseSensitive: boolean;
        wholeWord: boolean;
        regex: boolean;
    },
): ChapterMatch[] {
    const pattern = buildPattern(params);
    if (!pattern) return [];

    const matches: ChapterMatch[] = [];

    for (const scene of scenes) {
        const editor = registry.get(scene.id);
        if (!editor || editor.isDestroyed) continue;

        editor.state.doc.descendants((node, pos) => {
            if (!node.isText || !node.text) return;
            pattern.lastIndex = 0;
            let match: RegExpExecArray | null;
            while ((match = pattern.exec(node.text)) !== null) {
                matches.push({
                    sceneId: scene.id,
                    from: pos + match.index,
                    to: pos + match.index + match[0].length,
                });
                if (match[0].length === 0) break;
            }
        });
    }

    return matches;
}

export default function ChapterFindBar({
    editorRegistry,
    scenes,
    scrollContainerRef,
    showReplace,
    onClose,
}: {
    editorRegistry: RefObject<Map<number, Editor>>;
    scenes: Scene[];
    scrollContainerRef: RefObject<HTMLDivElement | null>;
    showReplace: boolean;
    onClose: () => void;
}) {
    const [query, setQuery] = useState('');
    const [replaceText, setReplaceText] = useState('');
    const [caseSensitive, setCaseSensitive] = useState(false);
    const [wholeWord, setWholeWord] = useState(false);
    const [useRegex, setUseRegex] = useState(false);
    const [allMatches, setAllMatches] = useState<ChapterMatch[]>([]);
    const [activeMatchIndex, setActiveMatchIndex] = useState(-1);

    const inputRef = useRef<HTMLInputElement>(null);
    const collectTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const rafRef = useRef(0);

    const searchParams = useMemo(
        () => ({
            query: query.trim(),
            caseSensitive,
            wholeWord,
            regex: useRegex,
        }),
        [query, caseSensitive, wholeWord, useRegex],
    );

    useEffect(() => {
        inputRef.current?.focus();
    }, []);

    const pushHighlights = useCallback(
        (params: typeof searchParams, activeMatch?: ChapterMatch) => {
            for (const [sceneId, editor] of editorRegistry.current) {
                if (editor.isDestroyed) continue;
                updateSearchHighlight(editor, {
                    ...params,
                    activeFrom:
                        activeMatch?.sceneId === sceneId
                            ? activeMatch.from
                            : -1,
                    activeTo:
                        activeMatch?.sceneId === sceneId ? activeMatch.to : -1,
                });
            }
        },
        [editorRegistry],
    );

    useEffect(() => {
        return () => {
            if (collectTimerRef.current) clearTimeout(collectTimerRef.current);
            cancelAnimationFrame(rafRef.current);
            for (const [, editor] of editorRegistry.current) {
                updateSearchHighlight(editor, {
                    query: '',
                    caseSensitive: false,
                    wholeWord: false,
                    regex: false,
                });
            }
        };
    }, [editorRegistry]);

    const doCollect = useCallback(
        (preserveIndex?: number) => {
            if (!searchParams.query) {
                setAllMatches([]);
                setActiveMatchIndex(-1);
                pushHighlights(searchParams);
                return;
            }

            const matches = collectMatches(
                scenes,
                editorRegistry.current,
                searchParams,
            );
            setAllMatches(matches);

            let newIndex: number;
            if (
                preserveIndex !== undefined &&
                preserveIndex >= 0 &&
                matches.length > 0
            ) {
                newIndex = Math.min(preserveIndex, matches.length - 1);
            } else {
                newIndex = matches.length > 0 ? 0 : -1;
            }
            setActiveMatchIndex(newIndex);
            pushHighlights(searchParams, matches[newIndex]);
        },
        [searchParams, scenes, editorRegistry, pushHighlights],
    );

    // Stable ref for doCollect so the editor update listener doesn't churn
    const doCollectRef = useRef(doCollect);
    doCollectRef.current = doCollect;

    useEffect(() => {
        if (collectTimerRef.current) clearTimeout(collectTimerRef.current);
        collectTimerRef.current = setTimeout(doCollect, 150);
        return () => {
            if (collectTimerRef.current) clearTimeout(collectTimerRef.current);
        };
    }, [doCollect]);

    // Re-collect when editors change content (user typing while find is open)
    useEffect(() => {
        const registry = editorRegistry.current;
        const handlers: Array<() => void> = [];

        const onEditorUpdate = () => {
            if (collectTimerRef.current) clearTimeout(collectTimerRef.current);
            collectTimerRef.current = setTimeout(
                () => doCollectRef.current(),
                300,
            );
        };

        for (const [, editor] of registry) {
            editor.on('update', onEditorUpdate);
            handlers.push(() => editor.off('update', onEditorUpdate));
        }

        return () => handlers.forEach((h) => h());
    }, [editorRegistry, scenes]);

    // Navigate to a specific match
    const navigateToMatch = useCallback(
        (index: number) => {
            if (allMatches.length === 0 || index < 0) return;

            const match = allMatches[index];
            const editor = editorRegistry.current.get(match.sceneId);
            if (!editor || editor.isDestroyed) return;

            setActiveMatchIndex(index);
            pushHighlights(searchParams, match);

            editor.commands.focus();
            editor.commands.setTextSelection({
                from: match.from,
                to: match.to,
            });

            cancelAnimationFrame(rafRef.current);
            rafRef.current = requestAnimationFrame(() => {
                const container = scrollContainerRef.current;
                if (!container) return;

                try {
                    const coords = editor.view.coordsAtPos(match.from);
                    const containerRect = container.getBoundingClientRect();
                    const relativeTop = coords.top - containerRect.top;

                    if (
                        relativeTop < 80 ||
                        relativeTop > containerRect.height - 80
                    ) {
                        const targetCenter = containerRect.height / 2;
                        container.scrollTo({
                            top:
                                container.scrollTop +
                                (relativeTop - targetCenter),
                            behavior: 'smooth',
                        });
                    }
                } catch {
                    // coordsAtPos can throw if position is invalid
                }
            });
        },
        [
            allMatches,
            editorRegistry,
            scrollContainerRef,
            searchParams,
            pushHighlights,
        ],
    );

    const nextMatch = useCallback(() => {
        if (allMatches.length === 0) return;
        const next = (activeMatchIndex + 1) % allMatches.length;
        navigateToMatch(next);
    }, [allMatches.length, activeMatchIndex, navigateToMatch]);

    const prevMatch = useCallback(() => {
        if (allMatches.length === 0) return;
        const prev =
            (activeMatchIndex - 1 + allMatches.length) % allMatches.length;
        navigateToMatch(prev);
    }, [allMatches.length, activeMatchIndex, navigateToMatch]);

    const replaceCurrent = useCallback(() => {
        if (activeMatchIndex < 0 || activeMatchIndex >= allMatches.length)
            return;

        const match = allMatches[activeMatchIndex];
        const editor = editorRegistry.current.get(match.sceneId);
        if (!editor) return;

        editor
            .chain()
            .setTextSelection({ from: match.from, to: match.to })
            .insertContent(replaceText)
            .run();

        // Re-collect and advance to the next match position
        if (collectTimerRef.current) clearTimeout(collectTimerRef.current);
        collectTimerRef.current = setTimeout(
            () => doCollectRef.current(activeMatchIndex),
            50,
        );
    }, [activeMatchIndex, allMatches, editorRegistry, replaceText]);

    const replaceAllMatches = useCallback(() => {
        if (!searchParams.query) return;

        // Re-collect from live doc state to avoid stale positions
        const freshMatches = collectMatches(
            scenes,
            editorRegistry.current,
            searchParams,
        );
        if (freshMatches.length === 0) return;

        const byScene = new Map<number, ChapterMatch[]>();
        for (const match of freshMatches) {
            const list = byScene.get(match.sceneId) ?? [];
            list.push(match);
            byScene.set(match.sceneId, list);
        }

        for (const [sceneId, sceneMatches] of byScene) {
            const editor = editorRegistry.current.get(sceneId);
            if (!editor || editor.isDestroyed) continue;

            const tr = editor.state.tr;
            for (const match of [...sceneMatches].reverse()) {
                if (replaceText) {
                    tr.replaceWith(
                        match.from,
                        match.to,
                        editor.schema.text(replaceText),
                    );
                } else {
                    tr.delete(match.from, match.to);
                }
            }
            editor.view.dispatch(tr);
        }

        if (collectTimerRef.current) clearTimeout(collectTimerRef.current);
        collectTimerRef.current = setTimeout(() => doCollectRef.current(), 50);
    }, [searchParams, scenes, editorRegistry, replaceText]);

    const handleSearchKeyDown = useCallback(
        (e: React.KeyboardEvent) => {
            if (e.key === 'Escape') {
                onClose();
            } else if (e.key === 'Enter' && e.shiftKey) {
                e.preventDefault();
                prevMatch();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                nextMatch();
            }
        },
        [onClose, nextMatch, prevMatch],
    );

    const handleReplaceKeyDown = useCallback(
        (e: React.KeyboardEvent) => {
            if (e.key === 'Escape') {
                onClose();
            } else if (
                e.key === 'Enter' &&
                (e.metaKey || e.ctrlKey) &&
                e.shiftKey
            ) {
                e.preventDefault();
                replaceAllMatches();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                replaceCurrent();
            }
        },
        [onClose, replaceCurrent, replaceAllMatches],
    );

    const matchCountLabel =
        allMatches.length === 0
            ? query.trim()
                ? 'No results'
                : ''
            : `${activeMatchIndex + 1} of ${allMatches.length}`;

    return (
        <div className="absolute top-3 right-6 z-20 flex w-[400px] animate-slide-down flex-col gap-2 rounded-lg border border-border bg-surface p-3 shadow-lg">
            <div className="flex items-center gap-1.5">
                <div className="flex items-center gap-1">
                    <ToggleButton
                        label="Aa"
                        active={caseSensitive}
                        onClick={() => setCaseSensitive((p) => !p)}
                        title="Match Case"
                    />
                    <ToggleButton
                        label="W"
                        active={wholeWord}
                        onClick={() => setWholeWord((p) => !p)}
                        title="Whole Word"
                    />
                    <ToggleButton
                        label=".*"
                        active={useRegex}
                        onClick={() => setUseRegex((p) => !p)}
                        title="Use Regex"
                        mono
                    />
                </div>

                <div className="flex min-w-0 flex-1 items-center gap-2 rounded-md border border-border bg-surface px-2.5 py-1.5">
                    <Search size={13} className="shrink-0 text-ink-faint" />
                    <input
                        ref={inputRef}
                        type="text"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        onKeyDown={handleSearchKeyDown}
                        placeholder="Find in chapter..."
                        className="min-w-0 flex-1 bg-transparent text-[13px] text-ink outline-none placeholder:text-ink-faint"
                    />
                </div>

                {matchCountLabel && (
                    <span className="shrink-0 text-[11px] text-ink-faint">
                        {matchCountLabel}
                    </span>
                )}

                <button
                    onClick={prevMatch}
                    disabled={allMatches.length === 0}
                    title="Previous Match (Shift+Enter)"
                    className="flex size-6 items-center justify-center rounded text-ink-faint transition-colors hover:bg-neutral-bg hover:text-ink disabled:opacity-30"
                >
                    <ChevronUp size={14} />
                </button>
                <button
                    onClick={nextMatch}
                    disabled={allMatches.length === 0}
                    title="Next Match (Enter)"
                    className="flex size-6 items-center justify-center rounded text-ink-faint transition-colors hover:bg-neutral-bg hover:text-ink disabled:opacity-30"
                >
                    <ChevronDown size={14} />
                </button>
                <button
                    onClick={onClose}
                    title="Close (Escape)"
                    className="flex size-6 items-center justify-center rounded text-ink-faint transition-colors hover:bg-neutral-bg hover:text-ink"
                >
                    <X size={14} />
                </button>
            </div>

            {showReplace && (
                <div className="flex items-center gap-1.5">
                    <div className="flex min-w-0 flex-1 items-center gap-2 rounded-md border border-border bg-surface px-2.5 py-1.5">
                        <Replace
                            size={13}
                            className="shrink-0 text-ink-faint"
                        />
                        <input
                            type="text"
                            value={replaceText}
                            onChange={(e) => setReplaceText(e.target.value)}
                            onKeyDown={handleReplaceKeyDown}
                            placeholder="Replace with..."
                            className="min-w-0 flex-1 bg-transparent text-[13px] text-ink outline-none placeholder:text-ink-faint"
                        />
                    </div>
                    <button
                        onClick={replaceCurrent}
                        disabled={activeMatchIndex < 0}
                        title="Replace"
                        className="shrink-0 rounded-md border border-border px-2.5 py-1 text-[12px] font-medium text-ink transition-colors hover:bg-neutral-bg disabled:opacity-30"
                    >
                        Replace
                    </button>
                    <button
                        onClick={replaceAllMatches}
                        disabled={allMatches.length === 0}
                        title="Replace All (⌘⇧↵)"
                        className="shrink-0 rounded-md border border-border px-2.5 py-1 text-[12px] font-medium text-ink transition-colors hover:bg-neutral-bg disabled:opacity-30"
                    >
                        All
                    </button>
                </div>
            )}
        </div>
    );
}
