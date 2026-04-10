import Badge from '@/components/ui/Badge';
import { cn } from '@/lib/utils';

export type PanelId = 'wiki' | 'notes' | 'ai' | 'chat' | 'editorial';

export type AccessBarItemConfig = {
    id: PanelId;
    icon: React.ComponentType<{ size?: number }>;
    label: string;
    badge?: number;
};

function AccessBarItem({
    id,
    icon: Icon,
    label,
    isActive,
    badge,
    onClick,
}: {
    id: PanelId;
    icon: React.ComponentType<{ size?: number }>;
    label: string;
    isActive: boolean;
    badge?: number;
    onClick: () => void;
}) {
    return (
        <div className="group relative">
            <button
                type="button"
                data-access-bar={id}
                onClick={onClick}
                className={cn(
                    'flex size-8 items-center justify-center rounded-md transition-colors',
                    isActive
                        ? 'bg-neutral-bg text-ink'
                        : 'text-ink-muted hover:bg-neutral-bg hover:text-ink',
                )}
            >
                <Icon size={14} />
                {badge !== undefined && badge > 0 && (
                    <Badge className="absolute -top-1 -right-1 size-4 justify-center bg-ink px-0 py-0 text-[9px] text-surface">
                        {badge > 9 ? '9+' : badge}
                    </Badge>
                )}
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
        <aside className="flex h-full w-12 shrink-0 flex-col items-center gap-1 border-l border-border-light bg-surface-card pt-3">
            {items.map((item) => (
                <AccessBarItem
                    key={item.id}
                    id={item.id}
                    icon={item.icon}
                    label={item.label}
                    isActive={openPanels.has(item.id)}
                    badge={item.badge}
                    onClick={() => onToggle(item.id)}
                />
            ))}
        </aside>
    );
}
