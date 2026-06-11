import { BookOpen, Feather, MessageSquare } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import { Card, CardDescription, CardTitle } from '@/components/ui/Card';
import SectionLabel from '@/components/ui/SectionLabel';
import { getPlotTemplates } from '@/lib/plot-templates';
import type { PlotTemplate } from '@/lib/plot-templates';
import { cn } from '@/lib/utils';

type PlotEmptyStateProps = {
    onSelectTemplate: (template: PlotTemplate) => void;
    onStartCoach: () => void;
};

export default function PlotEmptyState({
    onSelectTemplate,
    onStartCoach,
}: PlotEmptyStateProps) {
    const { t } = useTranslation('plot');
    const templates = getPlotTemplates(t);

    return (
        <div className="flex min-h-0 flex-1 flex-col items-center overflow-y-auto px-10">
            <div className="my-auto flex flex-col items-center gap-7 py-12">
                <div className="flex flex-col items-center gap-3">
                    <div className="flex size-12 items-center justify-center rounded-full bg-neutral-bg">
                        <Feather size={24} className="text-ink-muted" />
                    </div>

                    <h2 className="text-center font-serif text-[32px] leading-10 font-semibold tracking-[-0.01em] text-ink">
                        {t('emptyState.title')}
                    </h2>

                    <p className="max-w-[520px] text-center text-sm leading-relaxed text-ink-muted">
                        {t('emptyState.subtitle')}
                    </p>
                </div>

                <Card className="flex w-full max-w-[640px] items-center gap-4 bg-surface-warm p-5">
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-accent-light">
                        <MessageSquare className="size-5 text-accent" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <CardTitle>{t('emptyState.coachCta.title')}</CardTitle>
                        <CardDescription className="mt-0.5 text-xs leading-relaxed">
                            {t('emptyState.coachCta.text')}
                        </CardDescription>
                    </div>
                    <Button
                        type="button"
                        variant="primary"
                        size="sm"
                        className="shrink-0"
                        onClick={onStartCoach}
                    >
                        {t('emptyState.coachCta.button')}
                    </Button>
                </Card>

                <div className="flex w-full max-w-[860px] items-center gap-3">
                    <div className="h-px flex-1 bg-border-light" />
                    <SectionLabel>{t('emptyState.orTemplates')}</SectionLabel>
                    <div className="h-px flex-1 bg-border-light" />
                </div>

                <div className="grid w-full max-w-[860px] grid-cols-3 gap-3">
                    {templates.map((template) => {
                        const totalBeats = template.acts.reduce(
                            (sum, act) => sum + act.beats.length,
                            0,
                        );
                        return (
                            <Card
                                key={template.key}
                                role="button"
                                tabIndex={0}
                                onClick={() => onSelectTemplate(template)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' || e.key === ' ') {
                                        e.preventDefault();
                                        onSelectTemplate(template);
                                    }
                                }}
                                className={cn(
                                    'flex cursor-pointer flex-col gap-3 border-border p-5 text-left transition-shadow hover:shadow-sm',
                                    template.featured && 'border-2 border-ink',
                                )}
                            >
                                {template.featured && (
                                    <Badge
                                        variant="warning"
                                        className="self-start"
                                    >
                                        {t('emptyState.fitsBadge')}
                                    </Badge>
                                )}

                                <h3 className="text-sm font-medium text-ink">
                                    {template.name}
                                </h3>

                                <p className="text-xs leading-relaxed text-ink-muted">
                                    {template.description}
                                </p>

                                <div className="flex items-center gap-1.5">
                                    <BookOpen
                                        size={12}
                                        className="shrink-0 text-accent"
                                    />
                                    <span className="text-[11px] font-medium text-ink-muted">
                                        {template.books}
                                    </span>
                                </div>

                                <span className="text-[11px] text-ink-faint">
                                    {template.actFlow}
                                </span>

                                <span className="text-[11px] text-ink-faint">
                                    {t('emptyState.templateMeta', {
                                        acts: template.acts.length,
                                        beats: totalBeats,
                                    })}
                                </span>
                            </Card>
                        );
                    })}
                </div>

                <p className="text-xs text-ink-faint italic">
                    {t('emptyState.hint')}
                </p>
            </div>
        </div>
    );
}
