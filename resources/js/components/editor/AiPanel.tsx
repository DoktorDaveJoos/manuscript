import { Link, router, usePage } from '@inertiajs/react';
import { Lock, PenTool, Pilcrow, Sparkles } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { beautify, revise } from '@/actions/App/Http/Controllers/AiController';
import { index as settingsIndex } from '@/actions/App/Http/Controllers/SettingsController';
import PanelHeader from '@/components/ui/PanelHeader';
import ProFeatureLock from '@/components/ui/ProFeatureLock';
import SectionLabel from '@/components/ui/SectionLabel';
import { useAiFeatures } from '@/hooks/useAiFeatures';
import { useChapterAnalysis } from '@/hooks/useChapterAnalysis';
import { getXsrfToken } from '@/lib/csrf';
import { cn } from '@/lib/utils';
import type {
    Analysis,
    Book,
    Chapter,
    Character,
    CharacterChapterPivot,
    InformationDelivery,
} from '@/types/models';

type ChapterCharacter = Character & { pivot: CharacterChapterPivot };
type AnalysisWithSummary = {
    score?: number;
    findings?: string[];
    summary?: string;
};

function DescriptionText({ children }: { children: React.ReactNode }) {
    return (
        <p className="text-[11px] leading-[1.4] text-ink-muted">{children}</p>
    );
}

type ScoreLabel = { text: string; textColor: string; dotColor: string };

function makeScoreLabeler(good: string, fair: string, weak: string) {
    return (score: number | null, empty: ScoreLabel): ScoreLabel => {
        if (score === null) return empty;
        if (score >= 7)
            return {
                text: good,
                textColor: 'text-ai-green',
                dotColor: 'bg-ai-green',
            };
        if (score >= 4)
            return {
                text: fair,
                textColor: 'text-status-revised',
                dotColor: 'bg-status-revised',
            };
        return {
            text: weak,
            textColor: 'text-red-600',
            dotColor: 'bg-red-600',
        };
    };
}

function makeFivePointLabeler(good: string, fair: string, weak: string) {
    return (score: number | null, empty: ScoreLabel): ScoreLabel => {
        if (score === null) return empty;
        if (score >= 4)
            return {
                text: good,
                textColor: 'text-ai-green',
                dotColor: 'bg-ai-green',
            };
        if (score >= 3)
            return {
                text: fair,
                textColor: 'text-status-revised',
                dotColor: 'bg-status-revised',
            };
        return {
            text: weak,
            textColor: 'text-red-600',
            dotColor: 'bg-red-600',
        };
    };
}

function infoLabel(text: string): ScoreLabel {
    return { text, textColor: 'text-ink-muted', dotColor: 'bg-ink-muted' };
}

function deliveryLabel(
    value: InformationDelivery | null,
    t: (key: string) => string,
    empty: ScoreLabel,
): ScoreLabel {
    if (!value) return empty;
    const label = t(`delivery.${value}`);
    if (value === 'organic' || value === 'mostly_organic') {
        return {
            text: label,
            textColor: 'text-ai-green',
            dotColor: 'bg-ai-green',
        };
    }
    if (value === 'mixed') {
        return {
            text: label,
            textColor: 'text-status-revised',
            dotColor: 'bg-status-revised',
        };
    }
    return { text: label, textColor: 'text-red-600', dotColor: 'bg-red-600' };
}

function CraftMetricRow({
    label,
    score,
    detail,
}: {
    label: string;
    score: ScoreLabel;
    detail?: string | null;
}) {
    return (
        <div className="flex flex-col gap-[3px]">
            <div className="flex flex-wrap items-baseline justify-between gap-x-2">
                <span className="text-[13px] font-medium text-ink">
                    {label}
                </span>
                <div className="flex items-center gap-1.5">
                    <span
                        className={cn(
                            'size-1.5 shrink-0 rounded-full',
                            score.dotColor,
                        )}
                    />
                    <span
                        className={cn('text-xs font-medium', score.textColor)}
                    >
                        {score.text}
                    </span>
                </div>
            </div>
            {detail && (
                <span className="text-[11px] leading-[1.4] text-ink-faint">
                    {detail}
                </span>
            )}
        </div>
    );
}

function LevelGroup({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex flex-col gap-3">
            <SectionLabel>{title}</SectionLabel>
            {children}
        </div>
    );
}

function FindingDot({ variant }: { variant: 'warning' | 'info' }) {
    return (
        <span
            className={cn(
                'mt-1.5 size-1.5 shrink-0 rounded-full',
                variant === 'warning' ? 'bg-accent' : 'bg-ink-muted',
            )}
        />
    );
}

function CharacterRow({
    character,
    roleText,
}: {
    character: ChapterCharacter;
    roleText: string;
}) {
    const initial = character.name.charAt(0).toUpperCase();

    return (
        <div className="flex items-center gap-2">
            <div className="flex size-[22px] shrink-0 items-center justify-center rounded-full bg-neutral-bg">
                <span className="text-[11px] font-semibold text-ink-muted">
                    {initial}
                </span>
            </div>
            <span className="text-[13px] font-medium text-ink-soft">
                {character.name}
            </span>
            <span className="text-[11px] text-ink-muted">{roleText}</span>
        </div>
    );
}

const MAX_FINDINGS_DISPLAYED = 3;

function collectFindings(
    analyses: Record<string, Analysis>,
): { text: string; variant: 'warning' | 'info' }[] {
    const items: { text: string; variant: 'warning' | 'info' }[] = [];
    for (const analysis of Object.values(analyses)) {
        const result = analysis.result as {
            findings?: string[];
            recommendations?: string[];
        } | null;
        if (!result) continue;
        for (const f of (result.findings ?? []).slice(
            0,
            MAX_FINDINGS_DISPLAYED,
        )) {
            items.push({ text: f, variant: 'warning' });
        }
        for (const r of (result.recommendations ?? []).slice(
            0,
            MAX_FINDINGS_DISPLAYED,
        )) {
            items.push({ text: r, variant: 'info' });
        }
    }
    return items;
}

function getNextChapterSuggestion(
    analyses: Record<string, Analysis>,
): string | null {
    const ncs = analyses['next_chapter_suggestion'];
    if (!ncs?.result) return null;
    const result = ncs.result as { suggestion?: string };
    return result.suggestion ?? null;
}

const actionButtonClass =
    'flex items-center justify-center gap-1.5 rounded-lg bg-ink px-3 py-[9px] text-[13px] font-medium text-surface transition-colors hover:bg-ink/90 disabled:opacity-50';

export default function AiPanel({
    characters,
    book,
    chapter,
    onClose,
    onError,
    chapterAnalyses,
}: {
    characters: ChapterCharacter[];
    book: Book;
    chapter: Chapter;
    onClose: () => void;
    onError?: (message: string) => void;
    chapterAnalyses?: Record<string, Analysis>;
}) {
    const { t, i18n } = useTranslation('ai');
    const pageUrl = usePage().url;
    const { visible, usable, licensed } = useAiFeatures();
    const aiEnabled = usable;

    const [isRunningProse, setIsRunningProse] = useState(false);
    const [isBeautifying, setIsBeautifying] = useState(false);
    const {
        status: analysisStatus,
        isAnalyzing,
        error: analysisError,
        analyses,
        handleAnalyze,
    } = useChapterAnalysis(
        book.id,
        chapter.id,
        chapter.analysis_status,
        chapterAnalyses,
    );

    const EMPTY_SCORE: ScoreLabel = {
        text: t('score.empty'),
        textColor: 'text-ink-faint',
        dotColor: 'bg-ink-faint',
    };
    const scoreLabel = makeScoreLabeler(
        t('score.good'),
        t('score.fair'),
        t('score.weak'),
    );
    const sensoryScoreLabel = makeFivePointLabeler(
        t('score.good'),
        t('score.fair'),
        t('score.weak'),
    );
    const densityScoreLabel = makeScoreLabeler(
        t('score.rich'),
        t('score.thin'),
        t('score.sparse'),
    );
    const plotScoreLabel = makeScoreLabeler(
        t('score.onTrack'),
        t('score.drifting'),
        t('score.offCourse'),
    );

    const handleRunProse = useCallback(async () => {
        if (!chapter.analyzed_at && !confirm(t('confirm.notAnalyzed'))) {
            return;
        }

        if (
            chapter.word_count > 8000 &&
            !confirm(
                t('confirm.longChapter', {
                    wordCount: chapter.word_count.toLocaleString(i18n.language),
                }),
            )
        ) {
            return;
        }

        setIsRunningProse(true);
        try {
            const response = await fetch(
                revise.url({ book: book.id, chapter: chapter.id }),
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': getXsrfToken(),
                    },
                },
            );

            if (!response.ok) throw new Error(t('error.prosePassFailed'));

            await response.text();
            router.reload();
        } catch (e) {
            onError?.(
                e instanceof Error ? e.message : t('error.prosePassFailed'),
            );
        } finally {
            setIsRunningProse(false);
        }
    }, [
        book.id,
        chapter.id,
        chapter.word_count,
        chapter.analyzed_at,
        onError,
        t,
        i18n.language,
    ]);

    const handleBeautify = useCallback(async () => {
        setIsBeautifying(true);
        try {
            const response = await fetch(
                beautify.url({ book: book.id, chapter: chapter.id }),
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': getXsrfToken(),
                    },
                },
            );

            if (!response.ok) throw new Error(t('error.beautifyFailed'));

            await response.text();
            router.reload();
        } catch (e) {
            onError?.(
                e instanceof Error ? e.message : t('error.beautifyFailed'),
            );
        } finally {
            setIsBeautifying(false);
        }
    }, [book.id, chapter.id, onError, t]);

    const findings = useMemo(() => collectFindings(analyses), [analyses]);
    const nextSuggestion = useMemo(
        () => getNextChapterSuggestion(analyses),
        [analyses],
    );

    // Chapter level
    const tensionLabel = scoreLabel(chapter.tension_score, EMPTY_SCORE);
    const emotionalLabel = scoreLabel(
        chapter.emotional_shift_magnitude,
        EMPTY_SCORE,
    );
    const pacingLabel = chapter.pacing_feel
        ? infoLabel(t(`pacing.${chapter.pacing_feel}`))
        : EMPTY_SCORE;

    // Prose level
    const sensoryLabel = sensoryScoreLabel(
        chapter.sensory_grounding,
        EMPTY_SCORE,
    );
    const infoDeliveryLabel = deliveryLabel(
        chapter.information_delivery,
        t,
        EMPTY_SCORE,
    );
    const densityAnalysis = analyses['density']?.result as {
        score?: number;
        recommendations?: string[];
    } | null;
    const densityLabel = densityScoreLabel(
        densityAnalysis?.score ?? null,
        EMPTY_SCORE,
    );
    const densityDetail = densityAnalysis?.recommendations?.[0] ?? null;

    // Scene level
    const scenePurposeLabel = chapter.scene_purpose
        ? infoLabel(t(`purpose.${chapter.scene_purpose}`))
        : EMPTY_SCORE;
    const avgHookScore =
        chapter.entry_hook_score != null && chapter.exit_hook_score != null
            ? Math.round(
                  (chapter.entry_hook_score + chapter.exit_hook_score) / 2,
              )
            : (chapter.entry_hook_score ?? chapter.exit_hook_score ?? null);
    const hookLabel = scoreLabel(avgHookScore, EMPTY_SCORE);

    // Manuscript level
    const consistencyAnalysis = analyses['character_consistency']
        ?.result as AnalysisWithSummary | null;
    const consistencyLabel = scoreLabel(
        consistencyAnalysis?.score ?? null,
        EMPTY_SCORE,
    );
    const consistencyDetail = consistencyAnalysis?.summary ?? null;
    const plotAnalysis = analyses['plot_deviation']
        ?.result as AnalysisWithSummary | null;
    const plotLabel = plotScoreLabel(plotAnalysis?.score ?? null, EMPTY_SCORE);
    const plotDetail = plotAnalysis?.summary ?? null;

    if (!visible) return null;

    return (
        <aside className="flex h-full shrink-0 flex-col border-l border-border-light bg-surface-sidebar">
            <PanelHeader
                title={t('headerTitle')}
                icon={<Sparkles size={14} className="text-ink-muted" />}
                onClose={onClose}
                suffix={
                    licensed ? (
                        <span
                            className={cn(
                                'size-1.5 rounded-full',
                                aiEnabled ? 'bg-ai-green' : 'bg-status-revised',
                            )}
                        />
                    ) : (
                        <Lock size={14} className="text-ink-faint" />
                    )
                }
            />

            {licensed ? (
                <div className="flex flex-1 flex-col gap-6 overflow-x-hidden overflow-y-auto p-5">
                    {/* Actions section */}
                    <div className="flex flex-col gap-2.5">
                        <SectionLabel>{t('section.actions')}</SectionLabel>
                        {aiEnabled ? (
                            <>
                                <button
                                    type="button"
                                    onClick={handleAnalyze}
                                    disabled={isAnalyzing}
                                    className={actionButtonClass}
                                >
                                    <Sparkles size={14} strokeWidth={2.5} />
                                    {isAnalyzing
                                        ? t('actions.analyzing')
                                        : t('actions.analyze')}
                                </button>
                                <DescriptionText>
                                    {t('actions.analyzeDescription')}
                                </DescriptionText>
                                {analysisError && (
                                    <p className="text-[11px] leading-[1.4] text-red-600">
                                        {analysisError}
                                    </p>
                                )}
                                <button
                                    type="button"
                                    onClick={handleBeautify}
                                    disabled={isBeautifying}
                                    className={actionButtonClass}
                                >
                                    <Pilcrow size={14} strokeWidth={2.5} />
                                    {isBeautifying
                                        ? t('actions.beautifying')
                                        : t('actions.beautify')}
                                </button>
                                <DescriptionText>
                                    {t('actions.beautifyDescription')}
                                </DescriptionText>
                            </>
                        ) : (
                            <DescriptionText>
                                {t('actions.notConfigured')}{' '}
                                <Link
                                    href={settingsIndex.url({
                                        query: { from: pageUrl },
                                    })}
                                    className="font-medium text-accent underline decoration-accent/30 hover:decoration-accent"
                                >
                                    {t('actions.configureSettings')}
                                </Link>
                            </DescriptionText>
                        )}
                    </div>

                    {/* Prose section */}
                    {aiEnabled && (
                        <div className="flex flex-col gap-2.5">
                            <SectionLabel>{t('section.prose')}</SectionLabel>
                            <button
                                type="button"
                                onClick={handleRunProse}
                                disabled={isRunningProse}
                                className={actionButtonClass}
                            >
                                <PenTool size={14} strokeWidth={2.5} />
                                {isRunningProse
                                    ? t('prose.running')
                                    : t('prose.runProsePass')}
                            </button>
                            <DescriptionText>
                                {t('prose.description')}
                            </DescriptionText>
                            <Link
                                href={settingsIndex.url({
                                    query: {
                                        from: pageUrl,
                                        section: 'revision-rules',
                                    },
                                })}
                                className="text-[11px] font-medium text-accent transition-colors hover:text-accent-dark"
                            >
                                {t('prose.settingsLink')}
                            </Link>
                        </div>
                    )}

                    {/* Craft Metrics */}
                    <div className="flex flex-col gap-4">
                        <SectionLabel className="text-[11px] text-ink">
                            {t('section.craftMetrics')}
                        </SectionLabel>
                        <LevelGroup title={t('level.chapter')}>
                            <CraftMetricRow
                                label={t('metric.tensionDynamics')}
                                score={tensionLabel}
                                detail={
                                    chapter.tension_score != null
                                        ? t('metric.tensionDetail', {
                                              score: chapter.tension_score,
                                              microTension:
                                                  chapter.micro_tension_score ??
                                                  '--',
                                          })
                                        : null
                                }
                            />
                            <CraftMetricRow
                                label={t('metric.emotionalArc')}
                                score={emotionalLabel}
                                detail={
                                    chapter.emotional_shift_magnitude != null
                                        ? t('metric.emotionalDetail', {
                                              open:
                                                  chapter.emotional_state_open ??
                                                  '?',
                                              close:
                                                  chapter.emotional_state_close ??
                                                  '?',
                                              magnitude:
                                                  chapter.emotional_shift_magnitude,
                                          })
                                        : null
                                }
                            />
                            <CraftMetricRow
                                label={t('metric.pacing')}
                                score={pacingLabel}
                            />
                        </LevelGroup>

                        <LevelGroup title={t('level.prose')}>
                            <CraftMetricRow
                                label={t('metric.sensoryDetail')}
                                score={sensoryLabel}
                                detail={
                                    chapter.sensory_grounding != null
                                        ? t('metric.sensoryDetailCount', {
                                              senses: chapter.sensory_grounding,
                                          })
                                        : null
                                }
                            />
                            <CraftMetricRow
                                label={t('metric.informationDelivery')}
                                score={infoDeliveryLabel}
                            />
                            <CraftMetricRow
                                label={t('metric.narrativeDensity')}
                                score={densityLabel}
                                detail={densityDetail}
                            />
                        </LevelGroup>

                        <LevelGroup title={t('level.scene')}>
                            <CraftMetricRow
                                label={t('metric.scenePurpose')}
                                score={scenePurposeLabel}
                                detail={
                                    chapter.scene_purpose
                                        ? (chapter.value_shift ??
                                          t('metric.noValueShift'))
                                        : null
                                }
                            />
                            <CraftMetricRow
                                label={t('metric.hooks')}
                                score={hookLabel}
                                detail={
                                    avgHookScore != null
                                        ? chapter.hook_type
                                            ? t('metric.hooksDetailWithType', {
                                                  entry:
                                                      chapter.entry_hook_score ??
                                                      '--',
                                                  exit:
                                                      chapter.exit_hook_score ??
                                                      '--',
                                                  hookType:
                                                      chapter.hook_type.replace(
                                                          '_',
                                                          ' ',
                                                      ),
                                              })
                                            : t('metric.hooksDetail', {
                                                  entry:
                                                      chapter.entry_hook_score ??
                                                      '--',
                                                  exit:
                                                      chapter.exit_hook_score ??
                                                      '--',
                                              })
                                        : null
                                }
                            />
                        </LevelGroup>

                        <LevelGroup title={t('level.manuscript')}>
                            <CraftMetricRow
                                label={t('metric.plotAlignment')}
                                score={plotLabel}
                                detail={plotDetail}
                            />
                            <CraftMetricRow
                                label={t('metric.consistency')}
                                score={consistencyLabel}
                                detail={consistencyDetail}
                            />
                        </LevelGroup>
                    </div>

                    {/* Findings */}
                    <div className="flex flex-col gap-2.5">
                        <SectionLabel>{t('section.findings')}</SectionLabel>
                        {findings.length > 0 ? (
                            <div className="flex flex-col gap-2.5">
                                {findings.map((f, i) => (
                                    <div key={i} className="flex gap-2">
                                        <FindingDot variant={f.variant} />
                                        <span className="text-[11px] leading-[1.4] text-ink-soft">
                                            {f.text}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-[11px] leading-[1.4] text-ink-muted italic">
                                {analysisStatus === 'completed'
                                    ? t('findings.none')
                                    : t('findings.noAnalysis')}
                            </p>
                        )}
                    </div>

                    {/* Next Chapter */}
                    <div className="flex flex-col gap-2">
                        <SectionLabel>{t('section.nextChapter')}</SectionLabel>
                        {nextSuggestion ? (
                            <p className="text-[11px] leading-[1.4] text-ink-soft">
                                {nextSuggestion}
                            </p>
                        ) : (
                            <p className="text-[11px] leading-[1.4] text-ink-soft">
                                {t('nextChapter.noSuggestion')}
                            </p>
                        )}
                        <button
                            type="button"
                            disabled={!nextSuggestion}
                            className="self-start text-xs font-medium text-accent transition-colors hover:text-accent-dark disabled:opacity-50"
                        >
                            {t('nextChapter.generateOutline')}
                        </button>
                    </div>

                    {/* In This Chapter — Characters */}
                    <div className="flex flex-col gap-2.5">
                        <SectionLabel>
                            {t('section.inThisChapter')}
                        </SectionLabel>
                        {characters.length > 0 ? (
                            <div className="flex flex-col gap-2.5">
                                {characters.map((character) => (
                                    <CharacterRow
                                        key={character.id}
                                        character={character}
                                        roleText={t(
                                            `role.${character.pivot.role}`,
                                        )}
                                    />
                                ))}
                            </div>
                        ) : (
                            <p className="text-[11px] leading-[1.4] text-ink-muted italic">
                                {t('characters.none')}
                            </p>
                        )}
                    </div>
                </div>
            ) : (
                <ProFeatureLock>
                    <div className="flex flex-1 flex-col gap-5 p-4 opacity-40">
                        <div className="flex flex-col gap-2.5">
                            <SectionLabel>{t('section.actions')}</SectionLabel>
                            <div className="h-9 rounded-md bg-border/50" />
                        </div>
                        <div className="flex flex-col gap-2.5">
                            <SectionLabel>
                                {t('section.craftMetrics')}
                            </SectionLabel>
                            <div className="flex flex-col gap-4">
                                <LevelGroup title={t('level.chapter')}>
                                    <CraftMetricRow
                                        label={t('metric.tensionDynamics')}
                                        score={EMPTY_SCORE}
                                    />
                                    <CraftMetricRow
                                        label={t('metric.emotionalArc')}
                                        score={EMPTY_SCORE}
                                    />
                                    <CraftMetricRow
                                        label={t('metric.pacing')}
                                        score={EMPTY_SCORE}
                                    />
                                </LevelGroup>
                                <LevelGroup title={t('level.prose')}>
                                    <CraftMetricRow
                                        label={t('metric.sensoryDetail')}
                                        score={EMPTY_SCORE}
                                    />
                                    <CraftMetricRow
                                        label={t('metric.density')}
                                        score={EMPTY_SCORE}
                                    />
                                </LevelGroup>
                            </div>
                        </div>
                    </div>
                </ProFeatureLock>
            )}
        </aside>
    );
}
