import { useTranslation } from 'react-i18next';
import type { Character, WikiEntry } from '@/types/models';
import { WikiEntryListItem } from './WikiEntryList';
import type { WikiTab } from './WikiTabBar';

type SearchGroup = {
    tab: WikiTab;
    items: (Character | WikiEntry)[];
};

const tabLabelKey: Record<WikiTab, string> = {
    characters: 'characters',
    location: 'locations',
    organization: 'organizations',
    item: 'items',
    lore: 'lore',
};

export default function WikiSearchResults({
    results,
    selectedId,
    onSelect,
}: {
    results: SearchGroup[];
    selectedId: number | null;
    onSelect: (id: number, tab: WikiTab) => void;
}) {
    const { t } = useTranslation('wiki');

    if (results.length === 0) {
        return (
            <div className="px-3 py-8 text-center text-[13px] text-ink-muted">
                {t('search.noResults')}
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-4">
            {results.map(({ tab, items }) => (
                <div key={tab}>
                    <div className="px-3 pb-1.5 text-[10px] font-semibold tracking-[0.08em] text-ink-faint uppercase">
                        {t(`tabs.${tabLabelKey[tab]}`)}
                    </div>
                    <div className="flex flex-col gap-0.5">
                        {items.map((item) => (
                            <WikiEntryListItem
                                key={item.id}
                                item={item}
                                tab={tab}
                                selectedId={selectedId}
                                onSelect={(id) => onSelect(id, tab)}
                                showSubtitle={false}
                            />
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}
