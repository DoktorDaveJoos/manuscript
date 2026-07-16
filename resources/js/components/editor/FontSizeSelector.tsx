import { Check, ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/DropdownMenu';

export const FONT_SIZES = [14, 16, 18, 20, 22, 24] as const;

export const DEFAULT_FONT_SIZE = 18;

export default function FontSizeSelector({
    value,
    onChange,
}: {
    value: number;
    onChange: (size: number) => void;
}) {
    const { t } = useTranslation('editor');
    const [open, setOpen] = useState(false);

    return (
        <DropdownMenu open={open} onOpenChange={setOpen}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    title={t('toolbar.fontSize')}
                    className="h-7 gap-0.5 px-1.5 text-ink-muted"
                >
                    <span>{value}</span>
                    <ChevronDown size={12} />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-[120px]">
                <DropdownMenuGroup>
                    {FONT_SIZES.map((size) => (
                        <DropdownMenuItem
                            key={size}
                            onSelect={() => onChange(size)}
                            className="gap-2 px-2 py-1.5"
                        >
                            <span className="flex size-3.5 shrink-0 items-center justify-center text-ink-muted">
                                {size === value && (
                                    <Check size={14} strokeWidth={2.5} />
                                )}
                            </span>
                            <span className="flex-1 text-ink">{size}px</span>
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
