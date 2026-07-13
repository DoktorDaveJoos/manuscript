import { Eye, EyeOff } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import PanelHeader from '@/components/ui/PanelHeader';
import SectionLabel from '@/components/ui/SectionLabel';
import type { StyleAnalysis, StyleCategory } from '@/lib/style/types';
import { cn } from '@/lib/utils';
import type { ProofreadingConfig, StyleCheckKey } from '@/types/models';

const CATEGORY_ORDER: StyleCategory[] = [
    'filler',
    'weakVerb',
    'filterWord',
    'cliche',
    'pattern',
    'repetition',
];

const CATEGORY_CHIP: Record<StyleCategory, string> = {
    filler: 'bg-ink-faint',
    weakVerb: 'bg-plot-turning-text',
    filterWord: 'bg-plot-setup-text',
    cliche: 'bg-plot-conflict-text',
    pattern: 'bg-plot-worldbuilding-text',
    repetition: 'bg-plot-resolution-text',
};

/** How many sentence bars the rhythm strip shows at most. */
const RHYTHM_BAR_LIMIT = 60;
/** Sentence length (words) that renders as a full-height bar. */
const RHYTHM_BAR_CEILING = 30;

export default function StylePanel({
    analyses,
    wordCount,
    config,
    mutedCategories,
    onToggleCategory,
    onClose,
}: {
    analyses: StyleAnalysis[];
    /** Live canonical count shared with the editor bar. */
    wordCount: number;
    config: ProofreadingConfig;
    mutedCategories: ReadonlySet<string>;
    onToggleCategory: (category: StyleCheckKey) => void;
    onClose: () => void;
}) {
    const { t } = useTranslation('editor');

    const counts = new Map<StyleCategory, number>();
    for (const analysis of analyses) {
        for (const finding of analysis.findings) {
            counts.set(
                finding.category,
                (counts.get(finding.category) ?? 0) + 1,
            );
        }
    }
    const available = analyses[0]?.available;

    const sentenceCount = analyses.reduce(
        (sum, a) => sum + a.stats.sentenceCount,
        0,
    );
    const scored = analyses.filter(
        (a) => a.stats.readability !== null && a.stats.wordCount > 0,
    );
    const scoredWords = scored.reduce((sum, a) => sum + a.stats.wordCount, 0);
    const readability =
        scoredWords > 0
            ? scored.reduce(
                  (sum, a) =>
                      sum + (a.stats.readability ?? 0) * a.stats.wordCount,
                  0,
              ) / scoredWords
            : null;
    const readabilityFormula = scored[0]?.stats.readabilityFormula ?? null;
    const withAdjectives = analyses.filter(
        (a) => a.stats.adjectiveRatio !== null && a.stats.wordCount > 0,
    );
    const adjectiveWords = withAdjectives.reduce(
        (sum, a) => sum + a.stats.wordCount,
        0,
    );
    const adjectiveRatio =
        adjectiveWords > 0
            ? withAdjectives.reduce(
                  (sum, a) =>
                      sum + (a.stats.adjectiveRatio ?? 0) * a.stats.wordCount,
                  0,
              ) / adjectiveWords
            : null;
    const sentenceLengths = analyses
        .flatMap((a) => a.stats.sentenceLengths)
        .slice(-RHYTHM_BAR_LIMIT);

    const rhythmVisible =
        config.style_checks.rhythm && !mutedCategories.has('rhythm');

    return (
        <div className="flex h-full flex-col bg-white dark:bg-surface-card">
            <PanelHeader title={t('style.panelTitle')} onClose={onClose} />
            <div className="flex-1 overflow-y-auto px-4 py-4">
                <SectionLabel>{t('style.categories')}</SectionLabel>
                <div className="mt-2 flex flex-col">
                    {CATEGORY_ORDER.map((category) => {
                        const settingsOff = !config.style_checks[category];
                        const unavailable = available?.[category] === false;
                        const muted = mutedCategories.has(category);
                        const inactive = settingsOff || unavailable || muted;
                        return (
                            <div
                                key={category}
                                className="flex h-8 items-center gap-2"
                            >
                                <span
                                    className={cn(
                                        'size-2.5 shrink-0 rounded-full',
                                        CATEGORY_CHIP[category],
                                        inactive && 'opacity-30',
                                    )}
                                />
                                <span
                                    className={cn(
                                        'flex-1 truncate text-[13px]',
                                        inactive
                                            ? 'text-ink-faint'
                                            : 'text-ink',
                                    )}
                                >
                                    {t(`style.category.${category}`)}
                                </span>
                                {settingsOff || unavailable ? (
                                    <span className="text-[11px] text-ink-faint">
                                        {t(
                                            settingsOff
                                                ? 'style.disabledInSettings'
                                                : 'style.unavailable',
                                        )}
                                    </span>
                                ) : (
                                    <>
                                        <span className="text-[12px] text-ink-muted tabular-nums">
                                            {muted
                                                ? '–'
                                                : (counts.get(category) ?? 0)}
                                        </span>
                                        <button
                                            type="button"
                                            aria-label={t(
                                                muted
                                                    ? 'style.showCategory'
                                                    : 'style.muteCategory',
                                            )}
                                            onClick={() =>
                                                onToggleCategory(category)
                                            }
                                            className="flex size-6 items-center justify-center rounded text-ink-faint transition-colors hover:text-ink"
                                        >
                                            {muted ? (
                                                <EyeOff size={14} />
                                            ) : (
                                                <Eye size={14} />
                                            )}
                                        </button>
                                    </>
                                )}
                            </div>
                        );
                    })}
                </div>

                {rhythmVisible && (
                    <div className="mt-6">
                        <SectionLabel>{t('style.stats.title')}</SectionLabel>
                        {sentenceCount === 0 ? (
                            <p className="mt-2 text-[12px] text-ink-faint">
                                {t('style.analyzing')}
                            </p>
                        ) : (
                            <div className="mt-2 flex flex-col gap-1.5">
                                {readability !== null && readabilityFormula && (
                                    <StatRow
                                        label={t(
                                            `style.readability.${readabilityFormula}`,
                                        )}
                                        value={Math.round(
                                            readability,
                                        ).toString()}
                                    />
                                )}
                                <StatRow
                                    label={t('style.stats.avgSentence')}
                                    value={(wordCount / sentenceCount).toFixed(
                                        1,
                                    )}
                                />
                                <StatRow
                                    label={t('style.stats.sentences')}
                                    value={sentenceCount.toString()}
                                />
                                <StatRow
                                    label={t('style.stats.words')}
                                    value={wordCount.toString()}
                                />
                                {adjectiveRatio !== null && (
                                    <StatRow
                                        label={t('style.stats.adjectives')}
                                        value={`${(adjectiveRatio * 100).toFixed(1)}%`}
                                    />
                                )}
                                <div className="mt-2 flex h-8 items-end gap-px">
                                    {sentenceLengths.map((length, index) => (
                                        <span
                                            key={index}
                                            className="w-[3px] rounded-t bg-ink-faint/50"
                                            style={{
                                                height: `${Math.max(8, Math.min(1, length / RHYTHM_BAR_CEILING) * 100)}%`,
                                            }}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}

function StatRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-baseline justify-between">
            <span className="text-[12px] text-ink-muted">{label}</span>
            <span className="text-[13px] font-medium text-ink tabular-nums">
                {value}
            </span>
        </div>
    );
}
