import type { WikiEntryKind } from '@/types/models';

export type WikiTab = 'characters' | WikiEntryKind;

const tabs: { key: WikiTab; label: string }[] = [
    { key: 'characters', label: 'Characters' },
    { key: 'location', label: 'Locations' },
    { key: 'organization', label: 'Organizations' },
    { key: 'item', label: 'Items' },
    { key: 'lore', label: 'Lore' },
];

export default function WikiTabBar({
    activeTab,
    onTabChange,
}: {
    activeTab: WikiTab;
    onTabChange: (tab: WikiTab) => void;
}) {
    return (
        <div className="flex gap-5 border-b border-border-light">
            {tabs.map((tab) => (
                <button
                    key={tab.key}
                    onClick={() => onTabChange(tab.key)}
                    className={`pb-2.5 text-[13px] transition-colors ${
                        activeTab === tab.key
                            ? 'border-b-2 border-ink font-medium text-ink'
                            : 'text-ink-muted hover:text-ink'
                    }`}
                >
                    {tab.label}
                </button>
            ))}
        </div>
    );
}
