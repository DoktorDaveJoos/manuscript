import Sidebar from '@/components/editor/Sidebar';
import AddEntryDropdown from '@/components/wiki/AddEntryDropdown';
import CharacterDetail from '@/components/wiki/CharacterDetail';
import WikiCreateForm from '@/components/wiki/WikiCreateForm';
import WikiEditForm from '@/components/wiki/WikiEditForm';
import WikiEmptyState from '@/components/wiki/WikiEmptyState';
import WikiEntryDetail from '@/components/wiki/WikiEntryDetail';
import WikiEntryList from '@/components/wiki/WikiEntryList';
import WikiSearchInput from '@/components/wiki/WikiSearchInput';
import WikiSearchResults from '@/components/wiki/WikiSearchResults';
import WikiTabBar, { type WikiTab } from '@/components/wiki/WikiTabBar';
import { useSidebarStorylines } from '@/hooks/useSidebarStorylines';
import type { Book, Character, WikiEntry } from '@/types/models';
import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

const STORAGE_KEY = 'wiki-sidebar-width';
const MIN_WIDTH = 220;
const MAX_WIDTH = 480;
const DEFAULT_WIDTH = 280;

const validTabs: WikiTab[] = ['characters', 'location', 'organization', 'item', 'lore'];

type Props = {
    book: Book;
    characters: Character[];
    locations: WikiEntry[];
    organizations: WikiEntry[];
    items: WikiEntry[];
    lore: WikiEntry[];
    tab: string;
};

export default function WikiIndex({
    book,
    characters,
    locations,
    organizations,
    items,
    lore,
    tab,
}: Props) {
    const { t } = useTranslation('wiki');
    const storylines = useSidebarStorylines();
    const initialTab = validTabs.includes(tab as WikiTab) ? (tab as WikiTab) : 'characters';
    const [activeTab, setActiveTab] = useState<WikiTab>(initialTab);
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [query, setQuery] = useState('');
    const [creatingType, setCreatingType] = useState<WikiTab | null>(null);
    const [editingId, setEditingId] = useState<number | null>(null);
    const isSearching = query.trim().length > 0;

    const entriesByTab: Record<WikiTab, (Character | WikiEntry)[]> = {
        characters,
        location: locations,
        organization: organizations,
        item: items,
        lore,
    };

    const searchResults = useMemo(() => {
        if (!query.trim()) return [];
        const q = query.toLowerCase().trim();
        const groups: { tab: WikiTab; items: (Character | WikiEntry)[] }[] = [];

        for (const tab of validTabs) {
            const matched = entriesByTab[tab].filter((entry) => entry.name.toLowerCase().includes(q));
            if (matched.length > 0) groups.push({ tab, items: matched });
        }
        return groups;
    }, [query, characters, locations, organizations, items, lore]);

    const totalResults = searchResults.reduce((sum, g) => sum + g.items.length, 0);

    const handleSearchSelect = (id: number, tab: WikiTab) => {
        setActiveTab(tab);
        setSelectedId(id);
        setQuery('');
        setCreatingType(null);
        setEditingId(null);
    };

    const [width, setWidth] = useState(() => {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) {
            const parsed = Number(stored);
            if (parsed >= MIN_WIDTH && parsed <= MAX_WIDTH) return parsed;
        }
        return DEFAULT_WIDTH;
    });
    const widthRef = useRef(width);
    widthRef.current = width;
    const panelRef = useRef<HTMLDivElement>(null);
    const dragCleanupRef = useRef<(() => void) | null>(null);

    useEffect(() => {
        return () => dragCleanupRef.current?.();
    }, []);

    const handleMouseDown = useCallback((e: React.MouseEvent) => {
        e.preventDefault();
        const startX = e.clientX;
        const startWidth = widthRef.current;

        document.body.style.cursor = 'col-resize';
        document.body.style.userSelect = 'none';

        const cleanup = () => {
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            document.removeEventListener('mousemove', handleMouseMove);
            document.removeEventListener('mouseup', handleMouseUp);
            dragCleanupRef.current = null;
        };

        const handleMouseMove = (e: MouseEvent) => {
            const delta = e.clientX - startX;
            const newWidth = Math.min(MAX_WIDTH, Math.max(MIN_WIDTH, startWidth + delta));
            widthRef.current = newWidth;
            if (panelRef.current) panelRef.current.style.width = `${newWidth}px`;
        };

        const handleMouseUp = () => {
            setWidth(widthRef.current);
            localStorage.setItem(STORAGE_KEY, String(widthRef.current));
            cleanup();
        };

        dragCleanupRef.current = cleanup;
        document.addEventListener('mousemove', handleMouseMove);
        document.addEventListener('mouseup', handleMouseUp);
    }, []);

    const handleTabChange = (newTab: WikiTab) => {
        setSelectedId(null);
        setActiveTab(newTab);
        setCreatingType(null);
        setEditingId(null);
    };

    const handleAddEntry = (type: WikiTab) => {
        setActiveTab(type);
        setSelectedId(null);
        setEditingId(null);
        setCreatingType(type);
    };

    const handleEdit = (id: number) => {
        setEditingId(id);
        setCreatingType(null);
    };

    const currentItems = entriesByTab[activeTab] ?? [];
    const count = currentItems.length;

    const selectedCharacter =
        activeTab === 'characters'
            ? (characters.find((c) => c.id === selectedId) ?? null)
            : null;

    const selectedEntry =
        activeTab !== 'characters'
            ? ((currentItems as WikiEntry[]).find((e) => e.id === selectedId) ?? null)
            : null;

    const editingItem = editingId
        ? (currentItems.find((item) => item.id === editingId) ?? null)
        : null;

    // Determine what to render in the detail panel
    const renderDetailPanel = () => {
        if (creatingType) {
            return (
                <WikiCreateForm
                    type={creatingType}
                    book={book}
                    storylines={book.storylines ?? []}
                    onCancel={() => setCreatingType(null)}
                    onSuccess={() => setCreatingType(null)}
                />
            );
        }

        if (editingItem) {
            return (
                <WikiEditForm
                    item={editingItem}
                    tab={activeTab}
                    book={book}
                    storylines={book.storylines ?? []}
                    onCancel={() => setEditingId(null)}
                    onSuccess={() => setEditingId(null)}
                />
            );
        }

        if (selectedCharacter) {
            return <CharacterDetail character={selectedCharacter} onEdit={() => handleEdit(selectedCharacter.id)} />;
        }

        if (selectedEntry) {
            return <WikiEntryDetail entry={selectedEntry} tab={activeTab} onEdit={() => handleEdit(selectedEntry.id)} />;
        }

        return <WikiEmptyState />;
    };

    return (
        <>
            <Head title={t('pageTitle', { title: book.title })} />
            <div className="flex h-screen overflow-hidden bg-surface">
                <Sidebar book={book} storylines={storylines} scenesVisible={false} onScenesVisibleChange={() => {}} />

                {/* List panel */}
                <div ref={panelRef} className="relative flex shrink-0 flex-col border-r border-border-light bg-surface-card" style={{ width }}>
                    {/* Header */}
                    <div className="flex items-center justify-between px-5 pb-0 pt-5">
                        <div className="flex items-center gap-2">
                            <h1 className="text-[18px] font-semibold text-ink">{t('heading')}</h1>
                            <span className="text-[13px] text-ink-faint">
                                {isSearching ? t('search.results', { count: totalResults }) : count}
                            </span>
                        </div>
                        <AddEntryDropdown onSelect={handleAddEntry} />
                    </div>

                    {/* Search */}
                    <div className="px-3 pt-3 pb-1">
                        <WikiSearchInput query={query} onChange={setQuery} />
                    </div>

                    {/* Tabs — hidden during search */}
                    {!isSearching && (
                        <div className="px-5 pt-4">
                            <WikiTabBar activeTab={activeTab} onTabChange={handleTabChange} />
                        </div>
                    )}

                    {/* List or Search Results */}
                    <div className="flex-1 overflow-y-auto px-2 py-3">
                        {isSearching ? (
                            <WikiSearchResults
                                results={searchResults}
                                selectedId={selectedId}
                                onSelect={handleSearchSelect}
                            />
                        ) : (
                            <WikiEntryList
                                items={currentItems}
                                tab={activeTab}
                                selectedId={selectedId}
                                onSelect={setSelectedId}
                            />
                        )}
                    </div>

                    {/* Resize handle */}
                    <div
                        onMouseDown={handleMouseDown}
                        className="group absolute inset-y-0 -right-1 z-10 w-2 cursor-col-resize"
                    >
                        <div className="absolute inset-y-0 right-[3px] w-px bg-transparent transition-colors group-hover:bg-ink/20" />
                    </div>
                </div>

                {/* Detail panel */}
                <main className="flex min-w-0 flex-1 flex-col overflow-y-auto bg-surface px-12 py-10">
                    {renderDetailPanel()}
                </main>
            </div>
        </>
    );
}
