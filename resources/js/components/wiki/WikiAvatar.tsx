import { Building2, MapPin, ScrollText, Swords, User } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { WikiTab } from './WikiTabBar';

const AVATAR_PALETTE = [
    'bg-amber-100/40 text-amber-600/70 dark:bg-amber-900/20 dark:text-amber-400/60',
    'bg-blue-100/40 text-blue-600/70 dark:bg-blue-900/20 dark:text-blue-400/60',
    'bg-emerald-100/40 text-emerald-600/70 dark:bg-emerald-900/20 dark:text-emerald-400/60',
    'bg-violet-100/40 text-violet-600/70 dark:bg-violet-900/20 dark:text-violet-400/60',
    'bg-rose-100/40 text-rose-600/70 dark:bg-rose-900/20 dark:text-rose-400/60',
    'bg-cyan-100/40 text-cyan-600/70 dark:bg-cyan-900/20 dark:text-cyan-400/60',
    'bg-orange-100/40 text-orange-600/70 dark:bg-orange-900/20 dark:text-orange-400/60',
    'bg-indigo-100/40 text-indigo-600/70 dark:bg-indigo-900/20 dark:text-indigo-400/60',
] as const;

function hashColor(name: string): string {
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    return AVATAR_PALETTE[Math.abs(hash) % AVATAR_PALETTE.length];
}

const sizeClasses = {
    sm: 'h-8 w-8',
    md: 'h-10 w-10',
    lg: 'h-14 w-14',
} as const;

const iconSize = { sm: 14, md: 16, lg: 22 } as const;

const iconMap = {
    characters: User,
    location: MapPin,
    organization: Building2,
    item: Swords,
    lore: ScrollText,
} as const;

export default function WikiAvatar({
    name,
    tab,
    size = 'md',
}: {
    name: string;
    tab: WikiTab;
    size?: 'sm' | 'md' | 'lg';
}) {
    const Icon = iconMap[tab];

    return (
        <div
            className={cn(
                sizeClasses[size],
                'flex shrink-0 items-center justify-center rounded-xl',
                hashColor(name),
            )}
        >
            <Icon size={iconSize[size]} />
        </div>
    );
}
