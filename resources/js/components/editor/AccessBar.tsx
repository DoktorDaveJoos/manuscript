import { cn } from '@/lib/utils';

export type PanelId = 'notes' | 'ai' | 'chat';

export type AccessBarItemConfig = {
    id: PanelId;
    icon: React.ComponentType<{ size?: number }>;
    label: string;
};

function AccessBarItem({
    icon: Icon,
    label,
    isActive,
    onClick,
}: {
    icon: React.ComponentType<{ size?: number }>;
    label: string;
    isActive: boolean;
    onClick: () => void;
}) {
    return (
        <div className="group relative">
            <button
                type="button"
                onClick={onClick}
                className={cn(
                    'flex size-8 items-center justify-center rounded-md transition-colors',
                    isActive
                        ? 'bg-neutral-bg text-ink'
                        : 'text-ink-muted hover:bg-neutral-bg hover:text-ink',
                )}
            >
                <Icon size={14} />
            </button>
            <span className="pointer-events-none absolute top-1/2 right-full z-50 mr-2 -translate-y-1/2 rounded bg-ink px-2 py-1 text-[11px] whitespace-nowrap text-surface opacity-0 transition-opacity group-hover:opacity-100">
                {label}
            </span>
        </div>
    );
}

export default function AccessBar({
    items,
    openPanels,
    onToggle,
}: {
    items: AccessBarItemConfig[];
    openPanels: Set<PanelId>;
    onToggle: (panel: PanelId) => void;
}) {
    return (
        <aside className="flex h-full w-12 shrink-0 flex-col items-center gap-1 border-l border-border-light bg-white pt-3 dark:bg-surface-card">
            {items.map((item) => (
                <AccessBarItem
                    key={item.id}
                    icon={item.icon}
                    label={item.label}
                    isActive={openPanels.has(item.id)}
                    onClick={() => onToggle(item.id)}
                />
            ))}
        </aside>
    );
}
