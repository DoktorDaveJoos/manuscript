import { BookOpen } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { index as wikiIndex } from '@/actions/App/Http/Controllers/WikiController';
import {
    index as panelIndex,
    connect as panelConnect,
    disconnect as panelDisconnect,
    updateCharacter as panelUpdateCharacter,
    updateRole as panelUpdateRole,
    updateWikiEntry as panelUpdateWikiEntry,
} from '@/actions/App/Http/Controllers/WikiPanelController';
import PanelHeader from '@/components/ui/PanelHeader';
import SectionLabel from '@/components/ui/SectionLabel';
import { jsonFetchHeaders } from '@/lib/utils';
import type { Book, Chapter, Character, WikiEntry } from '@/types/models';
import WikiPanelCard from './WikiPanelCard';
import WikiPanelSearch from './WikiPanelSearch';
import type { SearchResult } from './WikiPanelSearch';

type ConnectedData = {
    characters: (Character & {
        chapters?: (Chapter & { pivot?: { role?: string } })[];
    })[];
    entries: WikiEntry[];
};

type PanelEntry = {
    entry: Character | WikiEntry;
    entryType: 'character' | 'wiki_entry';
    chapterRole?: string;
};

function removeSessionEntry(
    prev: PanelEntry[],
    entryType: string,
    id: number,
): PanelEntry[] {
    return prev.filter(
        (s) => !(s.entryType === entryType && s.entry.id === id),
    );
}

export default function WikiPanel({
    book,
    chapter,
    onClose,
}: {
    book: Book;
    chapter: Chapter;
    onClose: () => void;
}) {
    const { t } = useTranslation('wiki-panel');
    const [connected, setConnected] = useState<ConnectedData>({
        characters: [],
        entries: [],
    });
    const [sessionEntries, setSessionEntries] = useState<PanelEntry[]>([]);
    const [searchResults, setSearchResults] = useState<SearchResult[]>([]);
    const [expandedIds, setExpandedIds] = useState<Set<string>>(() => {
        try {
            const stored = localStorage.getItem(
                `manuscript:wiki-expanded:${chapter.id}`,
            );
            return stored ? new Set(JSON.parse(stored) as string[]) : new Set();
        } catch {
            return new Set();
        }
    });
    const [loading, setLoading] = useState(false);
    const abortRef = useRef<AbortController | null>(null);
    const searchAbortRef = useRef<AbortController | null>(null);

    const wikiUrl = wikiIndex.url({ book: book.id });

    const fetchConnected = useCallback(async () => {
        abortRef.current?.abort();
        const controller = new AbortController();
        abortRef.current = controller;

        setLoading(true);
        try {
            const res = await fetch(
                panelIndex.url(book.id, {
                    query: { chapter_id: chapter.id },
                }),
                {
                    headers: jsonFetchHeaders(),
                    signal: controller.signal,
                },
            );
            if (res.ok) {
                const data = await res.json();
                setConnected(data.connected);
            }
        } catch {
            // abort errors
        } finally {
            setLoading(false);
        }
    }, [book.id, chapter.id]);

    useEffect(() => {
        fetchConnected();
        setSessionEntries([]);
        // Restore expanded state for the new chapter
        try {
            const stored = localStorage.getItem(
                `manuscript:wiki-expanded:${chapter.id}`,
            );
            setExpandedIds(
                stored ? new Set(JSON.parse(stored) as string[]) : new Set(),
            );
        } catch {
            setExpandedIds(new Set());
        }
    }, [fetchConnected]);

    // Persist expanded state
    useEffect(() => {
        try {
            const key = `manuscript:wiki-expanded:${chapter.id}`;
            if (expandedIds.size > 0) {
                localStorage.setItem(key, JSON.stringify([...expandedIds]));
            } else {
                localStorage.removeItem(key);
            }
        } catch {
            /* no-op */
        }
    }, [expandedIds, chapter.id]);

    useEffect(() => {
        return () => {
            abortRef.current?.abort();
            searchAbortRef.current?.abort();
        };
    }, []);

    const handleSearch = useCallback(
        async (query: string) => {
            searchAbortRef.current?.abort();
            const controller = new AbortController();
            searchAbortRef.current = controller;

            try {
                const res = await fetch(
                    panelIndex.url(book.id, {
                        query: { chapter_id: chapter.id, q: query },
                    }),
                    {
                        headers: jsonFetchHeaders(),
                        signal: controller.signal,
                    },
                );
                if (res.ok) {
                    const data = await res.json();
                    setSearchResults(data.search_results ?? []);
                }
            } catch {
                // abort errors
            }
        },
        [book.id, chapter.id],
    );

    const handleSelectResult = useCallback(
        (result: SearchResult) => {
            setSessionEntries((prev) => {
                const exists = prev.some(
                    (s) =>
                        s.entryType === result.type && s.entry.id === result.id,
                );
                if (exists) return prev;

                const entry: PanelEntry =
                    result.type === 'character'
                        ? {
                              entry: {
                                  id: result.id,
                                  book_id: book.id,
                                  name: result.name,
                                  aliases: result.aliases,
                                  description: result.description,
                                  ai_description: null,
                                  first_appearance: null,
                                  storylines: null,
                                  is_ai_extracted: false,
                                  created_at: '',
                                  updated_at: '',
                              } as Character,
                              entryType: 'character',
                          }
                        : {
                              entry: {
                                  id: result.id,
                                  book_id: book.id,
                                  kind: result.kind as WikiEntry['kind'],
                                  name: result.name,
                                  type: result.entry_type,
                                  description: result.description,
                                  ai_description: null,
                                  first_appearance: null,
                                  metadata: null,
                                  is_ai_extracted: false,
                                  created_at: '',
                                  updated_at: '',
                              } as WikiEntry,
                              entryType: 'wiki_entry',
                          };

                return [...prev, entry];
            });
            setSearchResults([]);
        },
        [book.id],
    );

    const handleDismiss = useCallback((entryType: string, id: number) => {
        setSessionEntries((prev) => removeSessionEntry(prev, entryType, id));
    }, []);

    const handleConnect = useCallback(
        async (entryType: string, id: number, role?: string) => {
            try {
                await fetch(panelConnect.url(book.id), {
                    method: 'POST',
                    headers: jsonFetchHeaders(),
                    body: JSON.stringify({
                        chapter_id: chapter.id,
                        type: entryType,
                        id,
                        role,
                    }),
                });
                setSessionEntries((prev) =>
                    removeSessionEntry(prev, entryType, id),
                );
                fetchConnected();
            } catch {
                // ignore
            }
        },
        [book.id, chapter.id, fetchConnected],
    );

    const handleDisconnect = useCallback(
        async (entryType: string, id: number) => {
            try {
                await fetch(panelDisconnect.url(book.id), {
                    method: 'POST',
                    headers: jsonFetchHeaders(),
                    body: JSON.stringify({
                        chapter_id: chapter.id,
                        type: entryType,
                        id,
                    }),
                });
                fetchConnected();
            } catch {
                // ignore
            }
        },
        [book.id, chapter.id, fetchConnected],
    );

    const handleRoleChange = useCallback(
        async (characterId: number, role: string) => {
            try {
                await fetch(
                    panelUpdateRole.url({
                        book: book.id,
                        character: characterId,
                    }),
                    {
                        method: 'PATCH',
                        headers: jsonFetchHeaders(),
                        body: JSON.stringify({
                            chapter_id: chapter.id,
                            role,
                        }),
                    },
                );
                fetchConnected();
            } catch {
                // ignore
            }
        },
        [book.id, chapter.id, fetchConnected],
    );

    const handleUpdate = useCallback(
        async (
            entryType: string,
            id: number,
            data: Record<string, unknown>,
        ) => {
            const url =
                entryType === 'character'
                    ? panelUpdateCharacter.url({
                          book: book.id,
                          character: id,
                      })
                    : panelUpdateWikiEntry.url({
                          book: book.id,
                          wikiEntry: id,
                      });

            try {
                await fetch(url, {
                    method: 'PATCH',
                    headers: jsonFetchHeaders(),
                    body: JSON.stringify(data),
                });
            } catch {
                // silent fail
            }
        },
        [book.id],
    );

    const connectedList = useMemo<PanelEntry[]>(
        () => [
            ...connected.characters.map((c) => ({
                entry: c as Character,
                entryType: 'character' as const,
                chapterRole: c.chapters?.[0]?.pivot?.role ?? undefined,
            })),
            ...connected.entries.map((e) => ({
                entry: e as WikiEntry,
                entryType: 'wiki_entry' as const,
            })),
        ],
        [connected],
    );

    const makeCardKey = (entryType: string, id: number) => `${entryType}-${id}`;
    const toggleExpanded = (key: string) =>
        setExpandedIds((prev) => {
            const next = new Set(prev);
            if (next.has(key)) next.delete(key);
            else next.add(key);
            return next;
        });
    const isEmpty = connectedList.length === 0 && sessionEntries.length === 0;

    return (
        <aside className="flex h-full shrink-0 flex-col border-l border-border-light bg-surface-sidebar">
            <PanelHeader
                title={t('headerTitle')}
                icon={<BookOpen size={14} className="text-ink-muted" />}
                onClose={onClose}
            />

            <WikiPanelSearch
                results={searchResults}
                onSearch={handleSearch}
                onSelect={handleSelectResult}
            />

            <div className="flex flex-1 flex-col gap-3 overflow-y-auto p-4">
                {connectedList.length > 0 && (
                    <>
                        <SectionLabel variant="section">
                            {t('connectedToChapter')}
                        </SectionLabel>
                        {connectedList.map(
                            ({ entry, entryType, chapterRole }) => {
                                const key = makeCardKey(entryType, entry.id);
                                return (
                                    <WikiPanelCard
                                        key={key}
                                        entry={entry}
                                        entryType={entryType}
                                        isConnected
                                        isExpanded={expandedIds.has(key)}
                                        onToggleExpand={() =>
                                            toggleExpanded(key)
                                        }
                                        onDisconnect={() =>
                                            handleDisconnect(
                                                entryType,
                                                entry.id,
                                            )
                                        }
                                        onRoleChange={
                                            entryType === 'character'
                                                ? (role) =>
                                                      handleRoleChange(
                                                          entry.id,
                                                          role,
                                                      )
                                                : undefined
                                        }
                                        onUpdate={(data) =>
                                            handleUpdate(
                                                entryType,
                                                entry.id,
                                                data,
                                            )
                                        }
                                        chapterRole={chapterRole}
                                        wikiUrl={wikiUrl}
                                    />
                                );
                            },
                        )}
                    </>
                )}

                {sessionEntries.length > 0 && connectedList.length > 0 && (
                    <div className="flex items-center gap-2 py-1">
                        <div className="h-px flex-1 bg-border-light" />
                        <SectionLabel variant="section">
                            {t('recentlyViewed')}
                        </SectionLabel>
                        <div className="h-px flex-1 bg-border-light" />
                    </div>
                )}

                {sessionEntries.map(({ entry, entryType }) => {
                    const key = makeCardKey(entryType, entry.id);
                    return (
                        <WikiPanelCard
                            key={key}
                            entry={entry}
                            entryType={entryType}
                            isConnected={false}
                            isExpanded={expandedIds.has(key)}
                            onToggleExpand={() => toggleExpanded(key)}
                            onDismiss={() => handleDismiss(entryType, entry.id)}
                            onConnect={(role) =>
                                handleConnect(entryType, entry.id, role)
                            }
                            onUpdate={(data) =>
                                handleUpdate(entryType, entry.id, data)
                            }
                            wikiUrl={wikiUrl}
                        />
                    );
                })}

                {isEmpty && !loading && (
                    <p className="py-8 text-center text-[13px] text-ink-muted">
                        {t('emptyState')}
                    </p>
                )}
            </div>
        </aside>
    );
}
