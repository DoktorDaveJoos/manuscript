import { PLOT_TEMPLATES, type PlotTemplate } from '@/lib/plot-templates';
import { Layers } from 'lucide-react';
import { useTranslation } from 'react-i18next';

type PlotEmptyStateProps = {
    onSelectTemplate: (template: PlotTemplate) => void;
};

export default function PlotEmptyState({ onSelectTemplate }: PlotEmptyStateProps) {
    const { t } = useTranslation('plot');

    return (
        <div className="flex h-full flex-col items-center justify-center gap-3 px-10">
            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-neutral-bg">
                <Layers size={22} className="text-ink-muted" />
            </div>

            <h2 className="font-serif text-[28px] leading-9 tracking-[-0.01em] text-ink">
                {t('emptyState.title')}
            </h2>

            <p className="max-w-md text-center text-[14px] leading-[22px] text-ink-muted">
                {t('emptyState.subtitle')}
            </p>

            <div className="mt-5 flex gap-4">
                {PLOT_TEMPLATES.map((template) => {
                    const totalBeats = template.acts.reduce((sum, act) => sum + act.beats.length, 0);
                    return (
                        <button
                            key={template.key}
                            onClick={() => onSelectTemplate(template)}
                            className="group flex w-[220px] flex-col gap-3 rounded-xl border border-border bg-surface-card p-5 text-left transition-all hover:border-accent hover:shadow-sm"
                        >
                            <h3 className="text-[15px] font-semibold leading-5 text-ink">
                                {t(`emptyState.template.${template.key}.name`)}
                            </h3>
                            <p className="text-[13px] leading-[18px] text-ink-muted">
                                {t(`emptyState.template.${template.key}.description`)}
                            </p>
                            <span className="mt-auto text-[12px] text-ink-faint">
                                {t('emptyState.templateMeta', {
                                    acts: template.acts.length,
                                    beats: totalBeats,
                                })}
                            </span>
                        </button>
                    );
                })}
            </div>

            <p className="mt-3 text-[12px] text-ink-faint">
                {t('emptyState.hint')}
            </p>
        </div>
    );
}
