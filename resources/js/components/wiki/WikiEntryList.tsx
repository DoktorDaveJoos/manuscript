import { useTranslation } from 'react-i18next';
import type { Character, WikiEntry } from '@/types/models';
import WikiAvatar from './WikiAvatar';
import type { WikiTab } from './WikiTabBar';

type ListItem = Character | WikiEntry;

function useSubtitle(item: ListItem, tab: WikiTab): string {
    const { t } = useTranslation('wiki');

    if (tab === 'characters') {
        const char = item as Character;
        const chapterCount = char.chapters?.length ?? 0;
        const storylineCount = char.storylines?.length ?? 0;
        const storylinePart =
            storylineCount > 0
                ? ` · ${t('storylines', { count: storylineCount })}`
                : '';
        return `${t('chapters', { count: chapterCount })}${storylinePart}`;
    }

    const entry = item as WikiEntry;
    const chapterCount = entry.chapters?.length ?? 0;
    const typePart = entry.type ? ` · ${entry.type}` : '';
    return `${t('chapters', { count: chapterCount })}${typePart}`;
}

export default function WikiEntryList({
    items,
    tab,
    selectedId,
    onSelect,
}: {
    items: ListItem[];
    tab: WikiTab;
    selectedId: number | null;
    onSelect: (id: number) => void;
}) {
    if (items.length === 0) {
        return <WikiEntryListEmpty tab={tab} />;
    }

    return (
        <div className="flex flex-col gap-0.5">
            {items.map((item) => (
                <WikiEntryListItem
                    key={item.id}
                    item={item}
                    tab={tab}
                    selectedId={selectedId}
                    onSelect={onSelect}
                />
            ))}
        </div>
    );
}

export function WikiEntryListItem({
    item,
    tab,
    selectedId,
    onSelect,
    showSubtitle = true,
}: {
    item: ListItem;
    tab: WikiTab;
    selectedId: number | null;
    onSelect: (id: number) => void;
    showSubtitle?: boolean;
}) {
    const subtitle = useSubtitle(item, tab);

    return (
        <button
            onClick={() => onSelect(item.id)}
            className={`flex items-center gap-3 rounded-lg px-3 py-2.5 text-left transition-colors ${
                selectedId === item.id
                    ? 'bg-neutral-bg'
                    : 'hover:bg-neutral-bg/50'
            }`}
        >
            <WikiAvatar name={item.name} tab={tab} size="md" />
            <div className="min-w-0">
                <div className="truncate text-[13px] font-medium text-ink">
                    {item.name}
                </div>
                {showSubtitle && (
                    <div className="truncate text-[11px] text-ink-muted">
                        {subtitle}
                    </div>
                )}
            </div>
        </button>
    );
}

function WikiEntryListEmpty({ tab }: { tab: WikiTab }) {
    const { t } = useTranslation('wiki');

    return (
        <div className="flex flex-1 flex-col items-center justify-center px-6 py-16 text-center">
            <div className="mb-3">
                <WikiAvatar name="?" tab={tab} size="lg" />
            </div>
            <p className="text-[13px] font-medium text-ink">
                {t(`empty.${tab}.title`)}
            </p>
            <p className="mt-1.5 max-w-[240px] text-[12px] leading-relaxed text-ink-muted">
                {t(`empty.${tab}.description`)}
            </p>
        </div>
    );
}
