import { router } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronRight,
    Loader,
    Replace,
    Search,
} from 'lucide-react';
import { memo, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    replaceAll,
    search,
} from '@/actions/App/Http/Controllers/SearchController';
import Button from '@/components/ui/Button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/Collapsible';
import Kbd from '@/components/ui/Kbd';
import PanelHeader from '@/components/ui/PanelHeader';
import ToggleButton from '@/components/ui/ToggleButton';
import type { SearchHighlight } from '@/extensions/SearchHighlightExtension';
import { useResizablePanel } from '@/hooks/useResizablePanel';
import { cn, jsonFetchHeaders } from '@/lib/utils';

type MatchResult = {
    scene_id: number;
    scene_title: string;
    context: string;
    match_start: number;
    match_length: number;
};

type ChapterResult = {
    chapter_id: number;
    chapter_title: string;
    reader_order: number;
    matches: MatchResult[];
};

type SearchResponse = {
    results: ChapterResult[];
    total_matches: number;
    chapter_count: number;
};

export default function GlobalFindDrawer({
    bookId,
    currentChapterId,
    onClose,
    onNavigate,
    onSearchChange,
    showReplace,
}: {
    bookId: number;
    currentChapterId: number;
    onClose: () => void;
    onNavigate: (chapterId: number, sceneId: number) => void;
    onSearchChange?: (params: SearchHighlight | null) => void;
    showReplace: boolean;
}) {
    const {
        width,
        panelRef: asideRef,
        handleMouseDown,
    } = useResizablePanel({
        storageKey: 'manuscript:find-drawer-width',
        minWidth: 280,
        maxWidth: 500,
        defaultWidth: 320,
        direction: 'right',
    });

    const [query, setQuery] = useState('');
    const [replaceQuery, setReplaceQuery] = useState('');
    const [results, setResults] = useState<ChapterResult[]>([]);
    const [isSearching, setIsSearching] = useState(false);
    const [isReplacing, setIsReplacing] = useState(false);
    const [caseSensitive, setCaseSensitive] = useState(false);
    const [wholeWord, setWholeWord] = useState(false);
    const [useRegex, setUseRegex] = useState(false);
    const [expandedChapters, setExpandedChapters] = useState<Set<number>>(
        new Set(),
    );

    const totalMatches = useMemo(
        () => results.reduce((sum, ch) => sum + ch.matches.length, 0),
        [results],
    );

    const inputRef = useRef<HTMLInputElement>(null);
    const abortRef = useRef<AbortController | null>(null);

    useEffect(() => {
        inputRef.current?.focus();
        return () => {
            abortRef.current?.abort();
        };
    }, []);

    const performSearch = useCallback(async () => {
        const q = query.trim();
        if (!q) {
            setResults([]);
            return;
        }

        abortRef.current?.abort();
        const controller = new AbortController();
        abortRef.current = controller;

        setIsSearching(true);
        try {
            const response = await fetch(search.url(bookId), {
                method: 'POST',
                headers: jsonFetchHeaders(),
                body: JSON.stringify({
                    query: q,
                    case_sensitive: caseSensitive,
                    whole_word: wholeWord,
                    regex: useRegex,
                }),
                signal: controller.signal,
            });

            if (!response.ok) throw new Error('Search failed');

            const data: SearchResponse = await response.json();
            setResults(data.results);
            setExpandedChapters(new Set(data.results.map((r) => r.chapter_id)));
        } catch (e) {
            if ((e as Error).name !== 'AbortError') {
                setResults([]);
            }
        } finally {
            setIsSearching(false);
        }
    }, [bookId, query, caseSensitive, wholeWord, useRegex]);

    useEffect(() => {
        const q = query.trim();
        if (!q) {
            setResults([]);
            onSearchChange?.(null);
            return;
        }
        onSearchChange?.({
            query: q,
            caseSensitive,
            wholeWord,
            regex: useRegex,
        });
        const timer = setTimeout(performSearch, 300);
        return () => clearTimeout(timer);
    }, [
        query,
        caseSensitive,
        wholeWord,
        useRegex,
        performSearch,
        onSearchChange,
    ]);

    const handleReplaceAll = useCallback(async () => {
        if (!query.trim() || isReplacing) return;

        setIsReplacing(true);
        try {
            const response = await fetch(replaceAll.url(bookId), {
                method: 'POST',
                headers: jsonFetchHeaders(),
                body: JSON.stringify({
                    search: query,
                    replace: replaceQuery,
                    case_sensitive: caseSensitive,
                    whole_word: wholeWord,
                    regex: useRegex,
                }),
            });

            if (!response.ok) throw new Error('Replace failed');

            const data = await response.json();

            if (data.replaced_count > 0) {
                router.reload({
                    onFinish: () => performSearch(),
                });
            }
        } catch {
            // Ignore
        } finally {
            setIsReplacing(false);
        }
    }, [
        bookId,
        query,
        replaceQuery,
        caseSensitive,
        wholeWord,
        useRegex,
        isReplacing,
        performSearch,
    ]);

    const toggleChapter = useCallback((chapterId: number) => {
        setExpandedChapters((prev) => {
            const next = new Set(prev);
            if (next.has(chapterId)) {
                next.delete(chapterId);
            } else {
                next.add(chapterId);
            }
            return next;
        });
    }, []);

    const handleKeyDown = useCallback(
        (e: React.KeyboardEvent) => {
            if (e.key === 'Escape') {
                onClose();
            } else if (e.key === 'Enter' && !e.shiftKey) {
                performSearch();
            }
        },
        [onClose, performSearch],
    );

    return (
        <aside
            ref={asideRef}
            className="relative flex h-full shrink-0 flex-col border-l border-border-light bg-surface-sidebar"
            style={{ width }}
        >
            {/* Resize handle */}
            <div
                className="absolute inset-y-0 -left-1 z-10 w-2 cursor-col-resize hover:[&>div]:opacity-100"
                onMouseDown={handleMouseDown}
            >
                <div className="absolute inset-y-0 left-[3px] w-px bg-border opacity-0 transition-opacity" />
            </div>

            <PanelHeader
                title="Find in Book"
                icon={<Search size={14} className="text-ink-faint" />}
                onClose={onClose}
            />

            {/* Search Section */}
            <div className="flex flex-col gap-2 border-b border-border-light px-4 py-3">
                <div className="flex items-center gap-2 rounded-md border border-border bg-surface px-2.5 py-1.5">
                    <Search size={13} className="shrink-0 text-ink-faint" />
                    <input
                        ref={inputRef}
                        type="text"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        onKeyDown={handleKeyDown}
                        placeholder="Search all chapters..."
                        className="min-w-0 flex-1 bg-transparent text-[13px] text-ink outline-none placeholder:text-ink-faint"
                    />
                    {isSearching && (
                        <Loader
                            size={12}
                            className="animate-spin text-ink-faint"
                        />
                    )}
                </div>

                <div className="flex items-center justify-between">
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
                    {totalMatches > 0 && (
                        <span className="text-[11px] text-ink-faint">
                            {totalMatches} result
                            {totalMatches !== 1 ? 's' : ''} in {results.length}{' '}
                            ch.
                        </span>
                    )}
                </div>

                {showReplace && (
                    <div className="flex flex-col gap-2">
                        <div className="flex items-center gap-2 rounded-md border border-border bg-surface px-2.5 py-1.5">
                            <Replace
                                size={13}
                                className="shrink-0 text-ink-faint"
                            />
                            <input
                                type="text"
                                value={replaceQuery}
                                onChange={(e) =>
                                    setReplaceQuery(e.target.value)
                                }
                                onKeyDown={(e) => {
                                    if (e.key === 'Escape') onClose();
                                    else if (e.key === 'Enter')
                                        handleReplaceAll();
                                }}
                                placeholder="Replace with..."
                                className="min-w-0 flex-1 bg-transparent text-[13px] text-ink outline-none placeholder:text-ink-faint"
                            />
                        </div>
                        <Button
                            size="sm"
                            onClick={handleReplaceAll}
                            disabled={
                                !query.trim() ||
                                isReplacing ||
                                totalMatches === 0
                            }
                            className="w-full"
                        >
                            {isReplacing ? (
                                <Loader size={14} className="animate-spin" />
                            ) : (
                                'Replace All in Book'
                            )}
                        </Button>
                    </div>
                )}
            </div>

            {/* Results List */}
            <div className="flex-1 overflow-y-auto">
                {results.length === 0 && query.trim() && !isSearching ? (
                    <div className="px-4 py-8 text-center text-[12px] text-ink-faint">
                        No results found
                    </div>
                ) : (
                    results.map((chapter) => (
                        <ChapterGroup
                            key={chapter.chapter_id}
                            chapter={chapter}
                            isExpanded={expandedChapters.has(
                                chapter.chapter_id,
                            )}
                            isCurrentChapter={
                                chapter.chapter_id === currentChapterId
                            }
                            onToggle={() => toggleChapter(chapter.chapter_id)}
                            onResultClick={onNavigate}
                        />
                    ))
                )}
            </div>

            {/* Footer */}
            <div className="flex h-11 items-center justify-center border-t border-border-light">
                <Kbd keys="⌘⇧F" />
            </div>
        </aside>
    );
}

const ChapterGroup = memo(function ChapterGroup({
    chapter,
    isExpanded,
    isCurrentChapter,
    onToggle,
    onResultClick,
}: {
    chapter: ChapterResult;
    isExpanded: boolean;
    isCurrentChapter: boolean;
    onToggle: () => void;
    onResultClick: (chapterId: number, sceneId: number) => void;
}) {
    return (
        <Collapsible open={isExpanded} onOpenChange={onToggle}>
            <CollapsibleTrigger className="flex w-full items-start justify-between px-4 py-2 transition-colors hover:bg-neutral-bg">
                <div className="flex items-start gap-1.5 pt-px">
                    {isExpanded ? (
                        <ChevronDown size={12} className="text-ink-faint" />
                    ) : (
                        <ChevronRight size={12} className="text-ink-faint" />
                    )}
                    <span
                        className={cn(
                            'text-[12px] text-ink',
                            isCurrentChapter ? 'font-semibold' : 'font-medium',
                        )}
                    >
                        {chapter.chapter_title}
                    </span>
                </div>
                <span className="flex size-5 items-center justify-center rounded-full bg-neutral-bg text-[10px] font-medium text-ink-muted">
                    {chapter.matches.length}
                </span>
            </CollapsibleTrigger>
            <CollapsibleContent>
                {chapter.matches.map((match, i) => (
                    <ResultItem
                        key={`${match.scene_id}-${match.match_start}-${i}`}
                        match={match}
                        chapterId={chapter.chapter_id}
                        onClick={onResultClick}
                    />
                ))}
            </CollapsibleContent>
        </Collapsible>
    );
});

const ResultItem = memo(function ResultItem({
    match,
    chapterId,
    onClick,
}: {
    match: MatchResult;
    chapterId: number;
    onClick: (chapterId: number, sceneId: number) => void;
}) {
    return (
        <button
            onClick={() => onClick(chapterId, match.scene_id)}
            className="flex w-full flex-col items-start gap-0.5 px-4 py-1.5 pl-8 text-left transition-colors hover:bg-neutral-bg"
        >
            <span className="line-clamp-1 text-[12px] text-ink-soft">
                {match.context}
            </span>
            <span className="text-[11px] text-ink-faint">
                {match.scene_title}
            </span>
        </button>
    );
});
