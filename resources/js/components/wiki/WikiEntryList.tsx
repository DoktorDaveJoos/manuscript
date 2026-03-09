import type { Character, WikiEntry } from '@/types/models';
import WikiAvatar from './WikiAvatar';
import type { WikiTab } from './WikiTabBar';

type ListItem = Character | WikiEntry;

function getSubtitle(item: ListItem, tab: WikiTab): string {
    if (tab === 'characters') {
        const char = item as Character;
        const count = char.chapters?.length ?? 0;
        const storylines = char.storylines?.length
            ? ` · ${char.storylines.length} storyline${char.storylines.length > 1 ? 's' : ''}`
            : '';
        return `${count} chapter${count !== 1 ? 's' : ''}${storylines}`;
    }

    const entry = item as WikiEntry;
    const count = entry.chapters?.length ?? 0;
    const typePart = entry.type ? ` · ${entry.type}` : '';
    return `${count} chapter${count !== 1 ? 's' : ''}${typePart}`;
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
                <button
                    key={item.id}
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
                        <div className="truncate text-[11px] text-ink-muted">
                            {getSubtitle(item, tab)}
                        </div>
                    </div>
                </button>
            ))}
        </div>
    );
}

const emptyMessages: Record<WikiTab, { title: string; description: string }> = {
    characters: {
        title: 'No characters yet',
        description: 'Characters will appear here once extracted from your chapters by AI.',
    },
    location: {
        title: 'No locations yet',
        description: 'Track meaningful places in your story — cities, buildings, landscapes, or any setting that shapes the narrative.',
    },
    organization: {
        title: 'No organizations yet',
        description: 'Track groups, factions, institutions, or any collective entity in your story.',
    },
    item: {
        title: 'No items yet',
        description: 'Track meaningful objects in your story — letters, keys, artifacts, or anything that carries narrative weight.',
    },
    lore: {
        title: 'No lore yet',
        description: 'Track legends, histories, prophecies, or any world-building detail that enriches your story.',
    },
};

function WikiEntryListEmpty({ tab }: { tab: WikiTab }) {
    const { title, description } = emptyMessages[tab];

    return (
        <div className="flex flex-1 flex-col items-center justify-center px-6 py-16 text-center">
            <div className="mb-3">
                <WikiAvatar name="?" tab={tab} size="lg" />
            </div>
            <p className="text-[13px] font-medium text-ink">{title}</p>
            <p className="mt-1.5 max-w-[240px] text-[12px] leading-relaxed text-ink-muted">
                {description}
            </p>
        </div>
    );
}
