import { X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import Checkbox from '@/components/ui/Checkbox';
import {
    Command,
    CommandEmpty,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/Command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/Popover';
import SectionLabel from '@/components/ui/SectionLabel';
import type { Chapter } from '@/types/models';

type ChapterComboboxProps = {
    chapters: Chapter[];
    selectedIds: number[];
    onToggle: (id: number) => void;
};

export default function ChapterCombobox({
    chapters,
    selectedIds,
    onToggle,
}: ChapterComboboxProps) {
    const { t } = useTranslation('wiki');
    const [open, setOpen] = useState(false);

    const safeChapters = chapters ?? [];
    const safeSelectedIds = selectedIds ?? [];

    const selectedChapters = safeChapters.filter((ch) =>
        safeSelectedIds.includes(ch?.id),
    );

    return (
        <div className="flex flex-col gap-3">
            <SectionLabel variant="section">{t('appearsIn')}</SectionLabel>

            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <button
                        type="button"
                        className="flex w-full items-center gap-2 rounded-md border border-border bg-surface-card px-3 py-2 text-[13px] text-ink-faint transition-colors hover:border-ink-faint"
                    >
                        <span>{t('field.searchChapters')}</span>
                    </button>
                </PopoverTrigger>
                <PopoverContent className="max-h-[240px]">
                    <Command>
                        <CommandInput placeholder={t('field.searchChapters')} />
                        <CommandList className="max-h-[180px]">
                            <CommandEmpty>
                                {t('field.noChaptersFound')}
                            </CommandEmpty>
                            {safeChapters.map((ch) => {
                                const checked = safeSelectedIds.includes(ch.id);
                                return (
                                    <CommandItem
                                        key={ch.id}
                                        value={`${ch.reader_order} ${ch.title}`}
                                        onSelect={() => onToggle(ch.id)}
                                    >
                                        <Checkbox
                                            checked={checked}
                                            onChange={() => onToggle(ch.id)}
                                        />
                                        <span className="text-[13px] text-ink">
                                            {ch.reader_order}. {ch.title}
                                        </span>
                                    </CommandItem>
                                );
                            })}
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>

            {selectedChapters.length > 0 && (
                <div className="rounded-lg border border-border-light">
                    {selectedChapters.map((ch, i) => (
                        <div
                            key={ch.id}
                            className={`flex items-center justify-between px-3 py-2.5 ${
                                i > 0 ? 'border-t border-border-subtle' : ''
                            }`}
                        >
                            <span className="text-[13px] text-ink">
                                <span className="font-medium text-ink-muted">
                                    {ch.reader_order}.
                                </span>{' '}
                                {ch.title}
                            </span>
                            <button
                                type="button"
                                onClick={() => onToggle(ch.id)}
                                className="text-ink-faint transition-colors hover:text-ink"
                            >
                                <X size={14} />
                            </button>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
