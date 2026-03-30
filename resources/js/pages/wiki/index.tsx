import { Head } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import Sidebar from '@/components/editor/Sidebar';
import AddEntryDropdown from '@/components/wiki/AddEntryDropdown';
import CharacterDetail from '@/components/wiki/CharacterDetail';
import WikiEmptyState from '@/components/wiki/WikiEmptyState';
import WikiEntryDetail from '@/components/wiki/WikiEntryDetail';
import WikiEntryList from '@/components/wiki/WikiEntryList';
import WikiForm from '@/components/wiki/WikiForm';
import WikiSearchInput from '@/components/wiki/WikiSearchInput';
import WikiSearchResults from '@/components/wiki/WikiSearchResults';
import WikiTabBar from '@/components/wiki/WikiTabBar';
import type { WikiTab } from '@/components/wiki/WikiTabBar';
import { useFreeTier } from '@/hooks/useFreeTier';
import { useResizablePanel } from '@/hooks/useResizablePanel';
import { useSidebarStorylines } from '@/hooks/useSidebarStorylines';
import type { Book, Character, WikiEntry } from '@/types/models';

const validTabs: WikiTab[] = [
    'characters',
    'location',
    'organization',
    'item',
    'lore',
];

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
    const { isPro, isFree, wikiEntries: wikiLimits } = useFreeTier();
    const sidebarStorylines = useSidebarStorylines();
    const bookStorylines = book.storylines ?? [];
    const initialTab = validTabs.includes(tab as WikiTab)
        ? (tab as WikiTab)
        : 'characters';
    const [activeTab, setActiveTab] = useState<WikiTab>(initialTab);
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [query, setQuery] = useState('');
    const [creatingType, setCreatingType] = useState<WikiTab | null>(null);
    const [editingId, setEditingId] = useState<number | null>(null);
    const isSearching = query.trim().length > 0;

    const wikiLimit = wikiLimits?.limit ?? 5;
    const totalEntries =
        characters.length +
        locations.length +
        organizations.length +
        items.length +
        lore.length;
    const canAddEntry = isPro || totalEntries < wikiLimit;

    const entriesByTab = useMemo<Record<WikiTab, (Character | WikiEntry)[]>>(
        () => ({
            characters,
            location: locations,
            organization: organizations,
            item: items,
            lore,
        }),
        [characters, locations, organizations, items, lore],
    );

    const handleCreateSuccess = useCallback(() => {
        const tab = creatingType;
        setCreatingType(null);
        if (tab) {
            const list = entriesByTab[tab] ?? [];
            if (list.length > 0) {
                const newest = list.reduce((max, item) =>
                    item.id > max.id ? item : max,
                );
                setSelectedId(newest.id);
            }
        }
    }, [creatingType, entriesByTab]);

    const searchResults = useMemo(() => {
        if (!query.trim()) return [];
        const q = query.toLowerCase().trim();
        const groups: { tab: WikiTab; items: (Character | WikiEntry)[] }[] = [];

        for (const tab of validTabs) {
            const matched = entriesByTab[tab].filter((entry) =>
                entry.name.toLowerCase().includes(q),
            );
            if (matched.length > 0) groups.push({ tab, items: matched });
        }
        return groups;
    }, [query, entriesByTab]);

    const totalResults = searchResults.reduce(
        (sum, g) => sum + g.items.length,
        0,
    );

    const handleSearchSelect = (id: number, tab: WikiTab) => {
        setActiveTab(tab);
        setSelectedId(id);
        setQuery('');
        setCreatingType(null);
        setEditingId(null);
    };

    const { width, panelRef, handleMouseDown } = useResizablePanel({
        storageKey: 'wiki-sidebar-width',
        minWidth: 220,
        maxWidth: 480,
        defaultWidth: 280,
    });

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
            ? ((currentItems as WikiEntry[]).find((e) => e.id === selectedId) ??
              null)
            : null;

    const editingItem = editingId
        ? (currentItems.find((item) => item.id === editingId) ?? null)
        : null;

    // Determine what to render in the detail panel
    const renderDetailPanel = () => {
        if (creatingType) {
            return (
                <WikiForm
                    key={`create-${creatingType}`}
                    tab={creatingType}
                    book={book}
                    storylines={bookStorylines}
                    onCancel={() => setCreatingType(null)}
                    onSuccess={handleCreateSuccess}
                />
            );
        }

        if (editingItem) {
            return (
                <WikiForm
                    key={`edit-${editingItem.id}`}
                    item={editingItem}
                    tab={activeTab}
                    book={book}
                    storylines={bookStorylines}
                    onCancel={() => setEditingId(null)}
                    onSuccess={() => setEditingId(null)}
                />
            );
        }

        if (selectedCharacter) {
            return (
                <CharacterDetail
                    character={selectedCharacter}
                    storylines={bookStorylines}
                    onEdit={() => handleEdit(selectedCharacter.id)}
                />
            );
        }

        if (selectedEntry) {
            return (
                <WikiEntryDetail
                    entry={selectedEntry}
                    tab={activeTab}
                    onEdit={() => handleEdit(selectedEntry.id)}
                />
            );
        }

        return <WikiEmptyState />;
    };

    return (
        <>
            <Head title={t('pageTitle', { title: book.title })} />
            <div className="flex h-screen overflow-hidden bg-surface">
                <Sidebar
                    book={book}
                    storylines={sidebarStorylines}
                    scenesVisible={false}
                    onScenesVisibleChange={() => {}}
                />

                {/* List panel */}
                <div
                    ref={panelRef}
                    className="relative flex shrink-0 flex-col border-r border-border-light bg-surface-card"
                    style={{ width }}
                >
                    {/* Header */}
                    <div className="flex items-center justify-between px-5 pt-5 pb-0">
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-semibold tracking-[-0.01em] text-ink">
                                {t('heading')}
                            </h1>
                            <span className="text-[13px] text-ink-faint">
                                {isSearching
                                    ? t('search.results', {
                                          count: totalResults,
                                      })
                                    : isFree
                                      ? `${totalEntries}/${wikiLimit}`
                                      : count}
                            </span>
                        </div>
                        <AddEntryDropdown
                            onSelect={handleAddEntry}
                            disabled={!canAddEntry}
                        />
                    </div>

                    {/* Search */}
                    <div className="px-3 pt-3 pb-1">
                        <WikiSearchInput query={query} onChange={setQuery} />
                    </div>

                    {/* Tabs — hidden during search */}
                    {!isSearching && (
                        <div className="px-5 pt-4">
                            <WikiTabBar
                                activeTab={activeTab}
                                onTabChange={handleTabChange}
                            />
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
