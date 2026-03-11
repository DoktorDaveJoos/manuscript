import Sidebar from '@/components/editor/Sidebar';
import CharacterDetail from '@/components/wiki/CharacterDetail';
import WikiEmptyState from '@/components/wiki/WikiEmptyState';
import WikiEntryDetail from '@/components/wiki/WikiEntryDetail';
import WikiEntryList from '@/components/wiki/WikiEntryList';
import WikiTabBar, { type WikiTab } from '@/components/wiki/WikiTabBar';
import type { Book, Character, Storyline, WikiEntry } from '@/types/models';
import { Head } from '@inertiajs/react';
import { Plus } from '@phosphor-icons/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

const validTabs: WikiTab[] = ['characters', 'location', 'organization', 'item', 'lore'];

type Props = {
    book: Book & { storylines?: Storyline[] };
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
    const storylines = book.storylines ?? [];
    const initialTab = validTabs.includes(tab as WikiTab) ? (tab as WikiTab) : 'characters';
    const [activeTab, setActiveTab] = useState<WikiTab>(initialTab);
    const [selectedId, setSelectedId] = useState<number | null>(null);

    const handleTabChange = (newTab: WikiTab) => {
        setSelectedId(null);
        setActiveTab(newTab);
    };

    const entriesByTab: Record<WikiTab, (Character | WikiEntry)[]> = {
        characters,
        location: locations,
        organization: organizations,
        item: items,
        lore,
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

    return (
        <>
            <Head title={t('pageTitle', { title: book.title })} />
            <div className="flex h-screen overflow-hidden bg-surface">
                <Sidebar book={book} storylines={storylines} scenesVisible={false} onScenesVisibleChange={() => {}} />

                {/* List panel */}
                <div className="flex w-[280px] shrink-0 flex-col border-r border-border-light bg-surface-card">
                    {/* Header */}
                    <div className="flex items-center justify-between px-5 pb-0 pt-5">
                        <div className="flex items-center gap-2">
                            <h1 className="text-[18px] font-semibold text-ink">{t('heading')}</h1>
                            <span className="text-[13px] text-ink-faint">{count}</span>
                        </div>
                        <button className="flex h-6 w-6 items-center justify-center rounded border border-border text-ink-muted transition-colors hover:bg-neutral-bg">
                            <Plus size={12} weight="bold" />
                        </button>
                    </div>

                    {/* Tabs */}
                    <div className="px-5 pt-4">
                        <WikiTabBar activeTab={activeTab} onTabChange={handleTabChange} />
                    </div>

                    {/* List */}
                    <div className="flex-1 overflow-y-auto px-2 py-3">
                        <WikiEntryList
                            items={currentItems}
                            tab={activeTab}
                            selectedId={selectedId}
                            onSelect={setSelectedId}
                        />
                    </div>
                </div>

                {/* Detail panel */}
                <main className="flex min-w-0 flex-1 flex-col overflow-y-auto bg-surface px-12 py-10">
                    {selectedCharacter ? (
                        <CharacterDetail character={selectedCharacter} />
                    ) : selectedEntry ? (
                        <WikiEntryDetail entry={selectedEntry} tab={activeTab} />
                    ) : (
                        <WikiEmptyState />
                    )}
                </main>
            </div>
        </>
    );
}
