import { Building2, MapPin, ScrollText, Swords } from 'lucide-react';
import type { WikiTab } from './WikiTabBar';

export default function WikiAvatar({
    name,
    tab,
    size = 'md',
}: {
    name: string;
    tab: WikiTab;
    size?: 'sm' | 'md' | 'lg';
}) {
    const sizeClasses = {
        sm: 'h-8 w-8 text-[13px]',
        md: 'h-10 w-10 text-[15px]',
        lg: 'h-14 w-14 text-[20px]',
    };

    const iconSize = { sm: 14, md: 16, lg: 22 };

    if (tab === 'characters') {
        return (
            <div
                className={`${sizeClasses[size]} flex shrink-0 items-center justify-center rounded-full bg-neutral-bg font-medium text-ink-muted`}
            >
                {name.charAt(0).toUpperCase()}
            </div>
        );
    }

    const iconMap = {
        location: MapPin,
        organization: Building2,
        item: Swords,
        lore: ScrollText,
    };

    const Icon = iconMap[tab];

    return (
        <div
            className={`${sizeClasses[size]} flex shrink-0 items-center justify-center rounded-lg bg-neutral-bg text-ink-muted`}
        >
            <Icon size={iconSize[size]} />
        </div>
    );
}
