import type { TemplateDef } from '@/components/export/types';
import { cn } from '@/lib/utils';

const PREVIEW_TEXT =
    'The morning sun cast long shadows across the cobblestone path. She paused at the gate, clutching the letter that would change everything.';

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
                'flex h-[200px] w-[160px] shrink-0 flex-col overflow-hidden rounded-lg border p-4 text-left transition-all',
                'bg-white',
                'shadow-[0_2px_8px_#00000008] dark:shadow-[0_2px_8px_#00000020]',
                isSelected
                    ? 'border-accent ring-2 ring-accent'
                    : 'hover:border-border-strong border-border-light',
            )}
        >
            <span
                className="text-[13px] leading-tight font-semibold text-ink"
                style={{ fontFamily: template.headingFont }}
            >
                {template.name}
            </span>
            <p
                className="mt-2 line-clamp-6 text-[9px] leading-[1.6] text-ink-muted"
                style={{ fontFamily: template.bodyFont }}
            >
                {PREVIEW_TEXT}
            </p>
        </button>
    );
}
