import {
    ChevronLeft,
    ChevronRight,
    Cpu,
    Lightbulb,
    Sparkles,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import SectionLabel from '@/components/ui/SectionLabel';

const STORAGE_KEY = (bookId: number) =>
    `plot-coach-insights-collapsed-${bookId}`;

type ContextCounts = {
    acts: number;
    plotPoints: number;
    beats: number;
    characters: number;
    storylines: number;
    chapters: number;
};

type CoachInsightsPanelProps = {
    bookId: number;
    providerLabel: string | null;
    counts: ContextCounts;
    onHintClick: (hint: string) => void;
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

/**
 * Floating, sticky insights rail that sits to the left of the chat. Surfaces
 * the active model, what context the coach can already see, and a list of
 * idea-prompts the author can click as conversation starters.
 *
 * Hidden below `lg` because the chat column is centered at 720px — there's
 * only room to its left on wider viewports.
 */
export default function CoachInsightsPanel({
    bookId,
    providerLabel,
    counts,
    onHintClick,
}: CoachInsightsPanelProps) {
    const { t } = useTranslation('plot-coach');

    const [collapsed, setCollapsed] = useState<boolean>(() => {
        if (typeof window === 'undefined') return false;
        return window.localStorage.getItem(STORAGE_KEY(bookId)) === '1';
    });

    useEffect(() => {
        if (typeof window === 'undefined') return;
        window.localStorage.setItem(STORAGE_KEY(bookId), collapsed ? '1' : '0');
    }, [bookId, collapsed]);

    if (collapsed) {
        return (
            <div className="pointer-events-none absolute top-4 left-4 z-10 hidden lg:block">
                <Button
                    type="button"
                    variant="secondary"
                    size="icon"
                    onClick={() => setCollapsed(false)}
                    aria-label={t('insights.expand')}
                    title={t('insights.expand')}
                    className="pointer-events-auto size-8 bg-surface-card shadow-sm"
                >
                    <ChevronRight className="size-3.5" />
                </Button>
            </div>
        );
    }

    const contextRows = buildContextRows(counts, t);

    return (
        <div className="pointer-events-none absolute top-4 left-4 z-10 hidden w-[232px] lg:block">
            <div className="pointer-events-auto flex max-h-[calc(100vh-7rem)] flex-col gap-3 overflow-y-auto rounded-xl border border-border-light bg-surface-card p-3 shadow-sm">
                <div className="flex items-center justify-between">
                    <SectionLabel>{t('insights.title')}</SectionLabel>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={() => setCollapsed(true)}
                        aria-label={t('insights.collapse')}
                        title={t('insights.collapse')}
                        className="size-6"
                    >
                        <ChevronLeft className="size-3.5" />
                    </Button>
                </div>

                <ModelBlock
                    label={t('insights.model.label')}
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
        </div>
    );
}

function ModelBlock({
    label,
    providerLabel,
    fallback,
}: {
    label: string;
    providerLabel: string | null;
    fallback: string;
}) {
    return (
        <div className="flex flex-col gap-1">
            <span className="text-[11px] font-medium text-ink-muted">
                {label}
            </span>
            <div className="flex items-center gap-1.5 rounded-md border border-border-light bg-surface px-2 py-1.5">
                <Cpu className="size-3.5 shrink-0 text-ink-faint" />
                <span className="truncate text-xs text-ink">
                    {providerLabel ?? fallback}
                </span>
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
