import {
    Building2,
    BookOpen,
    Lock,
    MapPin,
    Package,
    Plus,
    User,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/DropdownMenu';
import { cn } from '@/lib/utils';
import type { WikiTab } from './WikiTabBar';

const options: { type: WikiTab; icon: typeof User }[] = [
    { type: 'characters', icon: User },
    { type: 'location', icon: MapPin },
    { type: 'organization', icon: Building2 },
    { type: 'item', icon: Package },
    { type: 'lore', icon: BookOpen },
];

export default function AddEntryDropdown({
    onSelect,
    disabled,
}: {
    onSelect: (type: WikiTab) => void;
    disabled?: boolean;
}) {
    const { t } = useTranslation('wiki');

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    disabled={disabled}
                    className={cn(
                        'flex h-[26px] w-[26px] items-center justify-center rounded-md border border-border bg-neutral-bg transition-colors',
                        disabled
                            ? 'cursor-default opacity-50'
                            : 'hover:bg-border',
                    )}
                    title={disabled ? 'Upgrade to Pro' : undefined}
                >
                    {disabled ? (
                        <Lock size={12} className="text-ink-faint" />
                    ) : (
                        <Plus size={14} className="text-ink-soft" />
                    )}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-[184px]">
                {options.map(({ type, icon: Icon }) => (
                    <DropdownMenuItem
                        key={type}
                        className="text-[14px] text-ink"
                        onSelect={() => onSelect(type)}
                    >
                        <Icon size={16} className="shrink-0 text-ink-soft" />
                        {t(
                            `dropdown.${type === 'characters' ? 'character' : type}`,
                        )}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
