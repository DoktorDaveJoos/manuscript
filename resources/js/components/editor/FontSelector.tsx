import { Check, ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/DropdownMenu';

export const FONTS = [
    {
        id: 'eb-garamond',
        label: 'EB Garamond',
        family: "'EB Garamond', ui-serif, Georgia, serif",
        favorite: true,
    },
    {
        id: 'palatino',
        label: 'Palatino',
        family: "Palatino, 'Palatino Linotype', 'Book Antiqua', serif",
        favorite: true,
    },
    {
        id: 'georgia',
        label: 'Georgia',
        family: "Georgia, 'Times New Roman', Times, serif",
        favorite: true,
    },
    {
        id: 'times',
        label: 'Times New Roman',
        family: "'Times New Roman', Times, serif",
        favorite: false,
    },
    {
        id: 'courier',
        label: 'Courier New',
        family: "'Courier New', Courier, monospace",
        favorite: false,
    },
] as const;

export const DEFAULT_FONT_ID = 'eb-garamond';

export function getFontFamily(fontId?: string): string {
    return FONTS.find((f) => f.id === fontId)?.family ?? FONTS[0].family;
}

export default function FontSelector({
    value,
    onChange,
}: {
    value: string;
    onChange: (fontId: string) => void;
}) {
    const { t } = useTranslation('editor');
    const [open, setOpen] = useState(false);

    const selected = FONTS.find((f) => f.id === value) ?? FONTS[0];
    const favorites = FONTS.filter((f) => f.favorite);
    const more = FONTS.filter((f) => !f.favorite);

    return (
        <DropdownMenu open={open} onOpenChange={setOpen}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    title={t('font.title')}
                    className="h-7 gap-1 px-2 text-ink-muted"
                >
                    <span
                        style={{ fontFamily: selected.family }}
                        className="text-[13px] leading-none"
                    >
                        {selected.label}
                    </span>
                    <ChevronDown
                        size={12}
                        strokeWidth={2.5}
                        className="shrink-0"
                    />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-[200px]">
                <DropdownMenuGroup>
                    <DropdownMenuLabel>{t('font.favorites')}</DropdownMenuLabel>
                    {favorites.map((font) => (
                        <FontItem
                            key={font.id}
                            font={font}
                            isSelected={font.id === value}
                            onSelect={() => onChange(font.id)}
                        />
                    ))}
                </DropdownMenuGroup>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    <DropdownMenuLabel>{t('font.more')}</DropdownMenuLabel>
                    {more.map((font) => (
                        <FontItem
                            key={font.id}
                            font={font}
                            isSelected={font.id === value}
                            onSelect={() => onChange(font.id)}
                        />
                    ))}
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function FontItem({
    font,
    isSelected,
    onSelect,
}: {
    font: (typeof FONTS)[number];
    isSelected: boolean;
    onSelect: () => void;
}) {
    return (
        <DropdownMenuItem onSelect={onSelect} className="gap-2 px-2 py-1.5">
            <span className="flex size-3.5 shrink-0 items-center justify-center text-ink-muted">
                {isSelected && <Check size={14} strokeWidth={2.5} />}
            </span>
            <span
                className="flex-1 text-ink"
                style={{ fontFamily: font.family }}
            >
                {font.label}
            </span>
        </DropdownMenuItem>
    );
}
