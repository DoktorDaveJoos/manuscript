import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import Textarea from '@/components/ui/Textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/ToggleGroup';
import DescriptionBlock from '@/components/wiki/DescriptionBlock';
import { cn } from '@/lib/utils';

type Mode = 'edit' | 'preview';

type MarkdownTextareaProps = {
    value: string;
    onChange: (value: string) => void;
    rows?: number;
    placeholder?: string;
    emptyPreviewText?: string;
    className?: string;
};

export default function MarkdownTextarea({
    value,
    onChange,
    rows = 4,
    placeholder,
    emptyPreviewText,
    className,
}: MarkdownTextareaProps) {
    const { t } = useTranslation('common');
    const [mode, setMode] = useState<Mode>('edit');

    return (
        <div className={cn('flex flex-col gap-1.5', className)}>
            <ToggleGroup
                type="single"
                value={mode}
                onValueChange={(next) => {
                    if (next === 'edit' || next === 'preview') {
                        setMode(next);
                    }
                }}
                className="self-end gap-0.5"
            >
                <ToggleGroupItem
                    value="edit"
                    className="px-2.5 py-0.5 text-[11px]"
                >
                    {t('edit')}
                </ToggleGroupItem>
                <ToggleGroupItem
                    value="preview"
                    className="px-2.5 py-0.5 text-[11px]"
                >
                    {t('preview')}
                </ToggleGroupItem>
            </ToggleGroup>

            {mode === 'edit' ? (
                <Textarea
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    rows={rows}
                    placeholder={placeholder}
                />
            ) : (
                <div
                    className="min-h-[6rem] rounded-md border border-border bg-surface-card px-3 py-2"
                    style={{ minHeight: `${rows * 1.5}rem` }}
                >
                    {value.trim() === '' ? (
                        <span className="text-[13px] text-ink-faint italic">
                            {emptyPreviewText ?? placeholder ?? ''}
                        </span>
                    ) : (
                        <DescriptionBlock
                            text={value}
                            className="text-[13px] leading-relaxed text-ink"
                        />
                    )}
                </div>
            )}
        </div>
    );
}
