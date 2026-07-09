import { Check } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { TemplateDef } from '@/components/export/types';
import { cn } from '@/lib/utils';

interface TemplateSelectorProps {
    templates: TemplateDef[];
    selectedTemplate: string;
    onChange: (slug: string) => void;
}

function TemplateRow({
    template,
    isSelected,
    onClick,
}: {
    template: TemplateDef;
    isSelected: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            role="radio"
            aria-checked={isSelected}
            onClick={onClick}
            className={cn(
                'flex w-full items-center justify-between gap-3 rounded-md border px-3 py-2 text-left transition-colors',
                isSelected
                    ? 'border-ink bg-surface-card'
                    : 'border-border-light bg-surface-card hover:border-ink-faint',
            )}
        >
            <span className="min-w-0">
                <span
                    className="block truncate text-[13px] font-medium text-ink"
                    style={{ fontFamily: template.headingFont }}
                >
                    {template.name}
                </span>
                <span className="block truncate text-[11px] text-ink-faint">
                    {template.bodyFont}
                </span>
            </span>
            {isSelected && <Check className="size-3.5 shrink-0 text-ink" />}
        </button>
    );
}

export default function TemplateSelector({
    templates,
    selectedTemplate,
    onChange,
}: TemplateSelectorProps) {
    const { t } = useTranslation('export');
    const builtIn = templates.filter((tpl) => tpl.group === 'builtin');
    const custom = templates.filter((tpl) => tpl.group === 'custom');

    return (
        <div
            role="radiogroup"
            aria-label={t('template')}
            className="flex max-h-56 flex-col gap-1.5 overflow-y-auto pr-1"
        >
            {builtIn.map((template) => (
                <TemplateRow
                    key={template.slug}
                    template={template}
                    isSelected={template.slug === selectedTemplate}
                    onClick={() => onChange(template.slug)}
                />
            ))}
            {custom.length > 0 && (
                <span className="mt-1.5 text-[11px] font-medium tracking-wide text-ink-muted uppercase">
                    {t('templateGroup.custom')}
                </span>
            )}
            {custom.map((template) => (
                <TemplateRow
                    key={template.slug}
                    template={template}
                    isSelected={template.slug === selectedTemplate}
                    onClick={() => onChange(template.slug)}
                />
            ))}
        </div>
    );
}
