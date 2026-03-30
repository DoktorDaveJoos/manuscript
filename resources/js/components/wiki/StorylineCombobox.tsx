import { X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import Badge from '@/components/ui/Badge';
import Checkbox from '@/components/ui/Checkbox';
import { Command, CommandItem, CommandList } from '@/components/ui/Command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/Popover';
import type { Storyline } from '@/types/models';

type StorylineComboboxProps = {
    storylines: Storyline[];
    selectedIds: number[];
    onToggle: (id: number) => void;
};

export default function StorylineCombobox({
    storylines,
    selectedIds,
    onToggle,
}: StorylineComboboxProps) {
    const { t } = useTranslation('wiki');
    const [open, setOpen] = useState(false);

    const safeStorylines = storylines ?? [];
    const safeSelectedIds = selectedIds ?? [];

    const selectedStorylines = safeStorylines.filter((s) =>
        safeSelectedIds.includes(s.id),
    );
    const hasSelected = selectedStorylines.length > 0;

    return (
        <div className="flex flex-col gap-1.5">
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <button
                        type="button"
                        className="flex w-full flex-wrap items-center gap-1.5 rounded-md border border-border bg-white px-3 py-2 text-left text-[13px] transition-colors hover:border-ink-faint dark:bg-surface-card"
                    >
                        {hasSelected ? (
                            selectedStorylines.map((s) => (
                                <Badge
                                    key={s.id}
                                    variant="secondary"
                                    className="gap-1"
                                >
                                    {s.name}
                                    <span
                                        role="button"
                                        tabIndex={0}
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            onToggle(s.id);
                                        }}
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter') {
                                                e.stopPropagation();
                                                onToggle(s.id);
                                            }
                                        }}
                                        className="text-ink-faint transition-colors hover:text-ink"
                                    >
                                        <X size={10} />
                                    </span>
                                </Badge>
                            ))
                        ) : (
                            <span className="text-ink-faint">
                                {t('field.addStoryline')}
                            </span>
                        )}
                    </button>
                </PopoverTrigger>
                <PopoverContent>
                    <Command>
                        <CommandList>
                            {safeStorylines.map((s) => {
                                const checked = safeSelectedIds.includes(s.id);
                                return (
                                    <CommandItem
                                        key={s.id}
                                        value={s.name}
                                        onSelect={() => onToggle(s.id)}
                                    >
                                        <Checkbox
                                            checked={checked}
                                            onChange={() => onToggle(s.id)}
                                        />
                                        <span className="text-[13px] text-ink">
                                            {s.name}
                                        </span>
                                    </CommandItem>
                                );
                            })}
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
        </div>
    );
}
