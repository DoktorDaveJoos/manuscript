import type { TemplateDef } from '@/components/export/types';
import { cn } from '@/lib/utils';

interface TemplateCardProps {
    template: TemplateDef;
    isSelected: boolean;
    onClick: () => void;
}

export default function TemplateCard({
    template,
    isSelected,
    onClick,
}: TemplateCardProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'flex w-[140px] shrink-0 flex-col gap-1.5 rounded-lg border p-3 text-left transition-all',
                isSelected
                    ? 'border-accent ring-2 ring-accent'
                    : 'hover:border-border-strong border-border-light',
            )}
        >
            <div className="flex items-start justify-between">
                <span
                    className="text-[13px] font-semibold text-ink"
                    style={{ fontFamily: template.headingFont }}
                >
                    {template.name}
                </span>
                <span className="rounded bg-neutral-bg px-1.5 py-0.5 text-[10px] font-medium text-ink-faint">
                    {template.pack}
                </span>
            </div>
            <span
                className="text-[15px] text-ink-muted"
                style={{ fontFamily: template.bodyFont }}
            >
                Aa Bb Cc
            </span>
        </button>
    );
}
