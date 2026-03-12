import type { WikiEntryKind } from '@/types/models';
import { useTranslation } from 'react-i18next';

export type WikiTab = 'characters' | WikiEntryKind;

const tabs = [
    { key: 'characters' as const, labelKey: 'tabs.characters' as const },
    { key: 'location' as const, labelKey: 'tabs.locations' as const },
    { key: 'organization' as const, labelKey: 'tabs.organizations' as const },
    { key: 'item' as const, labelKey: 'tabs.items' as const },
    { key: 'lore' as const, labelKey: 'tabs.lore' as const },
];

export default function WikiTabBar({
    activeTab,
    onTabChange,
}: {
    activeTab: WikiTab;
    onTabChange: (tab: WikiTab) => void;
}) {
    const { t } = useTranslation('wiki');

    return (
        <div className="relative border-b border-border-light">
            <div className="flex gap-5 overflow-x-auto [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                {tabs.map((tab) => (
                    <button
                        key={tab.key}
                        onClick={() => onTabChange(tab.key)}
                        className={`shrink-0 pb-2.5 text-[13px] whitespace-nowrap transition-colors ${
                            activeTab === tab.key
                                ? 'border-b-2 border-ink font-medium text-ink'
                                : 'text-ink-muted hover:text-ink'
                        }`}
                    >
                        {t(tab.labelKey)}
                    </button>
                ))}
            </div>
            {/* Right fade hint for scrollable overflow */}
            <div className="pointer-events-none absolute top-0 right-0 bottom-px h-full w-6 bg-gradient-to-l from-surface-card to-transparent" />
        </div>
    );
}
