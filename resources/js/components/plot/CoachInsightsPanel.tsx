import { Cpu, Lightbulb, Sparkles } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import PanelHeader from '@/components/ui/PanelHeader';

type ContextCounts = {
    acts: number;
    plotPoints: number;
    beats: number;
    characters: number;
    storylines: number;
    chapters: number;
};

type CoachInsightsPanelProps = {
    providerLabel: string | null;
    modelName: string | null;
    counts: ContextCounts;
    onHintClick: (hint: string) => void;
    onClose: () => void;
};

const HINT_KEYS = [
    'insights.hints.discuss_act',
    'insights.hints.beats_to_chapters',
    'insights.hints.fill_empty_plot_point',
    'insights.hints.tighten_descriptions',
    'insights.hints.find_duplicates',
    'insights.hints.what_is_missing',
    'insights.hints.thinner_motivation',
    'insights.hints.subplot_idea',
    'insights.hints.rehang_beat',
    'insights.hints.character_wound',
] as const;

export default function CoachInsightsPanel({
    providerLabel,
    modelName,
    counts,
    onHintClick,
    onClose,
}: CoachInsightsPanelProps) {
    const { t } = useTranslation('plot-coach');
    const contextRows = buildContextRows(counts, t);

    return (
        <aside className="flex h-full min-h-0 shrink-0 flex-col border-l border-border-light bg-surface-sidebar">
            <PanelHeader title={t('insights.title')} onClose={onClose} />
            <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-3">
                <ModelBlock
                    label={t('insights.model.label')}
                    modelName={modelName}
                    providerLabel={providerLabel}
                    fallback={t('insights.model.none')}
                />

                <ContextBlock
                    title={t('insights.context.title')}
                    rows={contextRows}
                    emptyLabel={t('insights.context.empty')}
                />

                <HintsBlock
                    title={t('insights.hints.title')}
                    subtitle={t('insights.hints.subtitle')}
                    hints={HINT_KEYS.map((key) => t(key))}
                    onHintClick={onHintClick}
                />
            </div>
        </aside>
    );
}

function ModelBlock({
    label,
    modelName,
    providerLabel,
    fallback,
}: {
    label: string;
    modelName: string | null;
    providerLabel: string | null;
    fallback: string;
}) {
    const primary = modelName ?? providerLabel ?? fallback;
    const secondary = modelName && providerLabel ? providerLabel : null;

    return (
        <div className="flex flex-col gap-1">
            <span className="text-[11px] font-medium text-ink-muted">
                {label}
            </span>
            <div className="flex items-center gap-1.5 rounded-md border border-border-light bg-surface px-2 py-1.5">
                <Cpu className="size-3.5 shrink-0 text-ink-faint" />
                <div className="flex min-w-0 flex-1 flex-col leading-tight">
                    <span className="truncate text-xs text-ink">{primary}</span>
                    {secondary && (
                        <span className="truncate text-[11px] text-ink-faint">
                            {secondary}
                        </span>
                    )}
                </div>
            </div>
        </div>
    );
}

type ContextRow = { key: string; label: string };

function ContextBlock({
    title,
    rows,
    emptyLabel,
}: {
    title: string;
    rows: ContextRow[];
    emptyLabel: string;
}) {
    return (
        <div className="flex flex-col gap-1.5">
            <span className="text-[11px] font-medium text-ink-muted">
                {title}
            </span>
            {rows.length === 0 ? (
                <p className="text-xs text-ink-faint">{emptyLabel}</p>
            ) : (
                <ul className="flex flex-col gap-1">
                    {rows.map((row) => (
                        <li
                            key={row.key}
                            className="flex items-center gap-1.5 text-xs text-ink-soft"
                        >
                            <span
                                aria-hidden
                                className="inline-block size-1.5 shrink-0 rounded-full bg-ai-green"
                            />
                            <span className="truncate">{row.label}</span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function HintsBlock({
    title,
    subtitle,
    hints,
    onHintClick,
}: {
    title: string;
    subtitle: string;
    hints: string[];
    onHintClick: (hint: string) => void;
}) {
    return (
        <div className="flex flex-col gap-1.5">
            <div className="flex items-center gap-1.5">
                <Lightbulb className="size-3.5 text-ink-faint" />
                <span className="text-[11px] font-medium text-ink-muted">
                    {title}
                </span>
            </div>
            <p className="text-[11px] text-ink-faint">{subtitle}</p>
            <div className="flex flex-col gap-1">
                {hints.map((hint, index) => (
                    <button
                        key={index}
                        type="button"
                        onClick={() => onHintClick(hint)}
                        className="group flex items-start gap-1.5 rounded-md border border-border-light bg-surface px-2 py-1.5 text-left text-xs leading-snug text-ink-soft transition-colors hover:border-border hover:bg-neutral-bg hover:text-ink"
                    >
                        <Sparkles className="mt-0.5 size-3 shrink-0 text-ink-faint group-hover:text-accent" />
                        <span className="flex-1">{hint}</span>
                    </button>
                ))}
            </div>
        </div>
    );
}

const CONTEXT_ROWS: ReadonlyArray<{
    key: string;
    countKey: keyof ContextCounts;
}> = [
    { key: 'acts', countKey: 'acts' },
    { key: 'plot_points', countKey: 'plotPoints' },
    { key: 'beats', countKey: 'beats' },
    { key: 'characters', countKey: 'characters' },
    { key: 'storylines', countKey: 'storylines' },
    { key: 'chapters', countKey: 'chapters' },
];

function buildContextRows(
    counts: ContextCounts,
    t: (key: string, opts?: Record<string, unknown>) => string,
): ContextRow[] {
    return CONTEXT_ROWS.flatMap(({ key, countKey }) => {
        const count = counts[countKey];
        if (count <= 0) return [];
        return [
            {
                key,
                label: t(`insights.context.${key}`, { count }),
            },
        ];
    });
}
