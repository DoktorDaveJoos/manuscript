import { BookOpen, Feather } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { getPlotTemplates } from '@/lib/plot-templates';
import type { PlotTemplate } from '@/lib/plot-templates';

type PlotEmptyStateProps = {
    onSelectTemplate: (template: PlotTemplate) => void;
};

export default function PlotEmptyState({
    onSelectTemplate,
}: PlotEmptyStateProps) {
    const { t } = useTranslation('plot');
    const templates = getPlotTemplates(t);

    return (
        <div className="flex h-full flex-col items-center justify-center px-10">
            <div className="flex flex-col items-center gap-7">
                <div className="flex flex-col items-center gap-3">
                    <Feather size={36} className="text-accent" />

                    <h2 className="text-center font-serif text-2xl leading-[1.2] font-normal text-ink">
                        {t('emptyState.title')}
                    </h2>

                    <p className="max-w-[480px] text-center text-[14px] leading-[1.6] whitespace-pre-line text-ink-muted">
                        {t('emptyState.subtitle')}
                    </p>
                </div>

                <div className="flex flex-wrap justify-center gap-3">
                    {templates.map((template) => {
                        const totalBeats = template.acts.reduce(
                            (sum, act) => sum + act.beats.length,
                            0,
                        );
                        return (
                            <button
                                key={template.key}
                                onClick={() => onSelectTemplate(template)}
                                className={`flex w-[210px] flex-col gap-2 rounded-xl p-5 pb-4 text-left transition-all hover:shadow-sm ${
                                    template.featured
                                        ? 'border-2 border-accent bg-surface-card'
                                        : 'border border-border bg-surface-card hover:border-accent'
                                }`}
                            >
                                {template.featured && (
                                    <span className="text-[11px] font-bold tracking-[0.06em] text-accent uppercase">
                                        {t('emptyState.fitsBadge')}
                                    </span>
                                )}

                                <h3 className="text-[14px] leading-tight font-semibold text-ink">
                                    {template.name}
                                </h3>

                                <p className="w-[170px] text-[12px] leading-[1.5] text-ink-muted">
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

                                <span
                                    className={`text-[11px] font-medium ${
                                        template.featured
                                            ? 'text-accent'
                                            : 'text-ink-faint'
                                    }`}
                                >
                                    {t('emptyState.select')}
                                </span>
                            </button>
                        );
                    })}
                </div>

                <p className="text-[12px] text-ink-faint italic">
                    {t('emptyState.hint')}
                </p>
            </div>
        </div>
    );
}
