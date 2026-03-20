import { Waypoints } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { getPlotTemplates } from '@/lib/plot-templates';
import type { GenreBadge, PlotTemplate } from '@/lib/plot-templates';

type PlotEmptyStateProps = {
    onSelectTemplate: (template: PlotTemplate) => void;
};

function ActBars({
    acts,
    height,
}: {
    acts: PlotTemplate['acts'];
    height: number;
}) {
    return (
        <div className="flex items-center gap-1">
            {acts.map((act, i) => (
                <div key={i} className="flex items-center gap-0.5">
                    {act.beats.map((_, j) => (
                        <div
                            key={j}
                            style={{
                                backgroundColor: act.color,
                                height,
                                width: height * 1.8,
                                borderRadius: height >= 10 ? 4 : 3,
                            }}
                        />
                    ))}
                </div>
            ))}
        </div>
    );
}

function GenreBadges({ genres }: { genres: GenreBadge[] }) {
    const { t } = useTranslation('plot');

    return (
        <div className="flex flex-wrap gap-1">
            {genres.map((genre) => (
                <span
                    key={genre.labelKey}
                    className="rounded-[10px] px-2 py-[3px] text-[10px] font-medium"
                    style={{
                        backgroundColor: genre.bgColor,
                        color: genre.textColor,
                    }}
                >
                    {t(genre.labelKey)}
                </span>
            ))}
        </div>
    );
}

export default function PlotEmptyState({
    onSelectTemplate,
}: PlotEmptyStateProps) {
    const { t } = useTranslation('plot');
    const templates = getPlotTemplates(t);
    const featured = templates.find((tpl) => tpl.featured);
    const others = templates.filter((tpl) => !tpl.featured);

    return (
        <div className="flex h-full flex-col items-center justify-center px-10">
            <div className="flex w-full max-w-[680px] flex-col items-center gap-3">
                <div className="flex h-14 w-14 items-center justify-center rounded-[14px] bg-gradient-to-br from-accent-light to-surface-warm">
                    <Waypoints size={28} className="text-accent" />
                </div>

                <h2 className="font-serif text-[26px] leading-[1.2] font-medium tracking-[-0.01em] text-ink">
                    {t('emptyState.title')}
                </h2>

                <p className="max-w-[500px] text-center text-[14px] leading-[1.6] text-ink-muted">
                    {t('emptyState.subtitle')}
                </p>

                {featured && (
                    <button
                        onClick={() => onSelectTemplate(featured)}
                        className="group mt-4 flex w-full flex-col gap-2 rounded-xl border-[1.5px] border-accent bg-accent-light px-6 py-5 text-left transition-all hover:shadow-sm"
                    >
                        <span className="self-start rounded-[10px] bg-accent/20 px-2 py-[3px] text-[10px] font-semibold text-accent">
                            {t('emptyState.popular')}
                        </span>
                        <ActBars acts={featured.acts} height={10} />
                        <h3 className="font-serif text-[18px] leading-6 font-medium text-ink">
                            {featured.name}
                        </h3>
                        <p className="text-[14px] leading-[1.5] text-ink-muted">
                            {featured.description}
                        </p>
                        <GenreBadges genres={featured.genres} />
                    </button>
                )}

                <div className="mt-4 grid w-full grid-cols-2 gap-3">
                    {others.map((template) => (
                        <button
                            key={template.key}
                            onClick={() => onSelectTemplate(template)}
                            className="group flex flex-col gap-2 rounded-xl border border-border bg-surface-card px-5 py-4 text-left transition-all hover:border-accent hover:shadow-sm"
                        >
                            <ActBars acts={template.acts} height={8} />
                            <h3 className="text-[15px] leading-5 font-semibold text-ink">
                                {template.name}
                            </h3>
                            <p className="text-[13px] leading-[1.5] text-ink-muted">
                                {template.description}
                            </p>
                            <GenreBadges genres={template.genres} />
                        </button>
                    ))}
                </div>

                <p className="text-[12px] text-ink-faint">
                    {t('emptyState.hint')}
                </p>
            </div>
        </div>
    );
}
