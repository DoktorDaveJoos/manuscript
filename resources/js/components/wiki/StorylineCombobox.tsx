import { Plus, X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import Checkbox from '@/components/ui/Checkbox';
import {
    Command,
    CommandGroup,
    CommandItem,
    CommandList,
} from '@/components/ui/Command';
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
            <div className="flex w-full flex-wrap items-center gap-1.5 rounded-md border border-border bg-surface-card px-2 py-1.5">
                {selectedStorylines.map((storyline) => (
                    <Badge
                        key={storyline.id}
                        variant="secondary"
                        className="gap-1"
                    >
                        {storyline.name}
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => onToggle(storyline.id)}
                            aria-label={t('field.removeStoryline', {
                                name: storyline.name,
                                defaultValue: `Remove ${storyline.name}`,
                            })}
                            className="size-4 text-ink-faint hover:text-ink"
                        >
                            <X size={12} />
                        </Button>
                    </Badge>
                ))}
                <Popover open={open} onOpenChange={setOpen}>
                    <PopoverTrigger asChild>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-6 min-w-0 flex-1 justify-start px-1 text-ink-faint"
                            aria-label={t('field.addStoryline')}
                        >
                            {hasSelected ? (
                                <Plus size={12} />
                            ) : (
                                t('field.addStoryline')
                            )}
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent>
                        <Command>
                            <CommandList>
                                <CommandGroup>
                                    {safeStorylines.map((storyline) => {
                                        const checked =
                                            safeSelectedIds.includes(
                                                storyline.id,
                                            );
                                        return (
                                            <CommandItem
                                                key={storyline.id}
                                                value={storyline.name}
                                                onSelect={() =>
                                                    onToggle(storyline.id)
                                                }
                                            >
                                                <Checkbox
                                                    checked={checked}
                                                    onChange={() =>
                                                        onToggle(storyline.id)
                                                    }
                                                />
                                                <span className="text-[13px] text-ink">
                                                    {storyline.name}
                                                </span>
                                            </CommandItem>
                                        );
                                    })}
                                </CommandGroup>
                            </CommandList>
                        </Command>
                    </PopoverContent>
                </Popover>
            </div>
        </div>
    );
}
