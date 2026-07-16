import { Building2, BookOpen, MapPin, Package, Plus, User } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/DropdownMenu';
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
}: {
    onSelect: (type: WikiTab) => void;
}) {
    const { t } = useTranslation('wiki');

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="secondary"
                    size="icon"
                    data-testid="add-wiki-entry"
                    aria-label={t('dropdown.addEntry')}
                    className="size-6"
                >
                    <Plus size={14} className="text-ink-soft" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-[184px]">
                <DropdownMenuGroup>
                    {options.map(({ type, icon: Icon }) => (
                        <DropdownMenuItem
                            key={type}
                            className="text-sm text-ink"
                            onSelect={() => onSelect(type)}
                        >
                            <Icon
                                size={16}
                                className="shrink-0 text-ink-soft"
                            />
                            {t(
                                `dropdown.${type === 'characters' ? 'character' : type}`,
                            )}
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
