import { revise } from '@/actions/App/Http/Controllers/AiController';
import ProFeatureLock from '@/components/ui/ProFeatureLock';
import { useAiFeatures } from '@/hooks/useAiFeatures';
import { useChapterAnalysis } from '@/hooks/useChapterAnalysis';
import { getXsrfToken } from '@/lib/csrf';
import { cn } from '@/lib/utils';
import type { Analysis, Book, Chapter, Character, CharacterChapterPivot, CharacterRole } from '@/types/models';
import { CaretLeft, CaretRight, ChatCircle, Lock, Sparkle, Table } from '@phosphor-icons/react';
import { Link, router } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';

type ChapterCharacter = Character & { pivot: CharacterChapterPivot };

const roleLabel: Record<CharacterRole, string> = {
    protagonist: 'POV',
    supporting: 'Supporting',
    mentioned: 'Mentioned',
};

function SectionLabel({ children }: { children: React.ReactNode }) {
    return (
        <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-faint">
            {children}
        </span>
    );
}

function SectionDivider() {
    return <div className="h-px bg-border-subtle" />;
}

type ScoreLabel = { text: string; textColor: string; dotColor: string };

const EMPTY_SCORE: ScoreLabel = { text: '--', textColor: 'text-ink-faint', dotColor: 'bg-ink-faint' };

function makeScoreLabeler(good: string, fair: string, weak: string) {
    return (score: number | null): ScoreLabel => {
        if (score === null) return EMPTY_SCORE;
        if (score >= 7) return { text: good, textColor: 'text-ai-green', dotColor: 'bg-ai-green' };
        if (score >= 4) return { text: fair, textColor: 'text-status-revised', dotColor: 'bg-status-revised' };
        return { text: weak, textColor: 'text-red-600', dotColor: 'bg-red-600' };
    };
}

const scoreLabel = makeScoreLabeler('Good', 'Fair', 'Weak');
const densityScoreLabel = makeScoreLabeler('Rich', 'Thin', 'Sparse');
const plotScoreLabel = makeScoreLabeler('On track', 'Drifting', 'Off course');

function presenceLabel(value: unknown): ScoreLabel {
    if (value != null) return { text: 'Strong', textColor: 'text-ai-green', dotColor: 'bg-ai-green' };
    return EMPTY_SCORE;
}

function CraftMetricRow({ label, score, detail }: { label: string; score: ScoreLabel; detail?: string | null }) {
    return (
        <div className="flex flex-col gap-[3px]">
            <div className="flex items-center justify-between">
                <span className="text-[13px] text-ink">{label}</span>
                <div className="flex items-center gap-1.5">
                    <span className={cn('size-1.5 rounded-full', score.dotColor)} />
                    <span className={cn('text-xs font-medium', score.textColor)}>{score.text}</span>
                </div>
            </div>
            {detail && (
                <span className="text-[11px] text-ink-faint">{detail}</span>
            )}
        </div>
    );
}

function LevelGroup({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <div className="flex flex-col gap-2">
            <span className="text-[10px] font-semibold uppercase tracking-[0.08em] text-ink-muted">
                {title}
            </span>
            {children}
        </div>
    );
}

function FindingDot({ variant }: { variant: 'warning' | 'info' }) {
    return (
        <span
            className={cn(
                'mt-1.5 size-[5px] shrink-0 rounded-full',
                variant === 'warning' ? 'bg-status-revised' : 'bg-ink-faint',
            )}
        />
    );
}

function CharacterRow({ character }: { character: ChapterCharacter }) {
    const initial = character.name.charAt(0).toUpperCase();

    return (
        <div className="flex items-center gap-2.5">
            <div className="flex size-[22px] shrink-0 items-center justify-center rounded-full bg-border">
                <span className="text-[10px] font-semibold text-ink-muted">{initial}</span>
            </div>
            <div className="flex flex-col">
                <span className="text-[13px] font-medium text-ink">{character.name}</span>
                <span className="text-[11px] text-ink-faint">{roleLabel[character.pivot.role]}</span>
            </div>
        </div>
    );
}

function CollapseIcon() {
    return <CaretRight size={14} weight="bold" />;
}

function ExpandIcon() {
    return <CaretLeft size={14} weight="bold" />;
}

function SparkleIcon() {
    return <Sparkle size={16} weight="fill" />;
}

function collectFindings(analyses: Record<string, Analysis>): { text: string; variant: 'warning' | 'info' }[] {
    const items: { text: string; variant: 'warning' | 'info' }[] = [];
    for (const analysis of Object.values(analyses)) {
        const result = analysis.result as { findings?: string[]; recommendations?: string[] } | null;
        if (!result) continue;
        for (const f of result.findings ?? []) {
            items.push({ text: f, variant: 'warning' });
        }
        for (const r of result.recommendations ?? []) {
            items.push({ text: r, variant: 'info' });
        }
    }
    return items;
}

function getNextChapterSuggestion(analyses: Record<string, Analysis>): string | null {
    const ncs = analyses['next_chapter_suggestion'];
    if (!ncs?.result) return null;
    const result = ncs.result as { suggestion?: string };
    return result.suggestion ?? null;
}

const actionButtonClass =
    'flex items-center justify-center gap-2 rounded-md bg-ink px-3 py-[9px] text-[13px] font-medium text-white transition-colors hover:bg-ink/90 disabled:opacity-50';

export default function AiPanel({
    characters,
    book,
    chapter,
    isOpen,
    onToggle,
    onError,
    onOpenChat,
    chapterAnalyses,
}: {
    characters: ChapterCharacter[];
    book: Book;
    chapter: Chapter;
    isOpen: boolean;
    onToggle: () => void;
    onError?: (message: string) => void;
    onOpenChat?: () => void;
    chapterAnalyses?: Record<string, Analysis>;
}) {
    const { visible, usable, licensed } = useAiFeatures();
    const aiEnabled = usable;

    if (!visible) return null;

    const [isRunningProse, setIsRunningProse] = useState(false);
    const { status: analysisStatus, isAnalyzing, error: analysisError, analyses, handleAnalyze } =
        useChapterAnalysis(book.id, chapter.id, chapter.analysis_status, chapterAnalyses);

    const handleRunProse = useCallback(async () => {
        if (
            !chapter.analyzed_at &&
            !confirm(
                "This chapter hasn't been analyzed yet. The prose pass works better with character and entity context. Continue anyway?",
            )
        ) {
            return;
        }

        if (
            chapter.word_count > 8000 &&
            !confirm(
                `This chapter has ${chapter.word_count.toLocaleString()} words. Very long chapters may produce lower quality revisions or exceed AI output limits. Continue?`,
            )
        ) {
            return;
        }

        setIsRunningProse(true);
        try {
            const response = await fetch(revise.url({ book: book.id, chapter: chapter.id }), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
            });

            if (!response.ok) throw new Error('Prose pass failed');

            await response.text();
            router.reload();
        } catch (e) {
            onError?.(e instanceof Error ? e.message : 'Prose pass failed');
        } finally {
            setIsRunningProse(false);
        }
    }, [book.id, chapter.id, chapter.word_count, chapter.analyzed_at, onError]);

    const findings = useMemo(() => collectFindings(analyses), [analyses]);
    const nextSuggestion = useMemo(() => getNextChapterSuggestion(analyses), [analyses]);

    // Chapter level
    const tensionLabel = scoreLabel(chapter.tension_score);
    const emotionalLabel = scoreLabel(chapter.emotional_shift_magnitude);
    const pacingAnalysis = analyses['pacing']?.result as { score?: number } | null;
    const pacingLabel = scoreLabel(pacingAnalysis?.score ?? null);

    // Prose level
    const craftScore = chapter.sensory_grounding != null ? chapter.sensory_grounding * 2 : null;
    const craftLabel = scoreLabel(craftScore);
    const densityAnalysis = analyses['density']?.result as { score?: number; recommendations?: string[] } | null;
    const densityLabel = densityScoreLabel(densityAnalysis?.score ?? null);
    const densityDetail = densityAnalysis?.recommendations?.[0] ?? null;

    // Scene level
    const scenePurposeLabel = presenceLabel(chapter.scene_purpose);
    const avgHookScore = chapter.entry_hook_score != null && chapter.exit_hook_score != null
        ? Math.round((chapter.entry_hook_score + chapter.exit_hook_score) / 2)
        : chapter.entry_hook_score ?? chapter.exit_hook_score ?? null;
    const hookLabel = scoreLabel(avgHookScore);

    // Manuscript level
    const consistencyAnalysis = analyses['character_consistency']?.result as { score?: number; findings?: string[] } | null;
    const consistencyLabel = scoreLabel(consistencyAnalysis?.score ?? null);
    const consistencyDetail = consistencyAnalysis?.findings?.[0] ?? null;
    const plotAnalysis = analyses['plot_deviation']?.result as { score?: number; findings?: string[] } | null;
    const plotLabel = plotScoreLabel(plotAnalysis?.score ?? null);
    const plotDetail = plotAnalysis?.findings?.[0] ?? null;

    return (
        <aside
            className={cn(
                'flex h-full shrink-0 flex-col border-l border-border bg-surface-card transition-[width] duration-200 ease-in-out',
                isOpen ? 'w-[280px]' : 'w-10',
            )}
        >
            {isOpen ? (
                <>
                    {/* Header */}
                    <div className="flex h-12 items-center justify-between border-b border-border-subtle px-5">
                        <div className="flex items-center gap-2">
                            <span className="text-xs font-semibold uppercase tracking-[0.06em] text-ink">
                                AI Assistant
                            </span>
                            {licensed ? (
                                <span
                                    className={cn(
                                        'size-1.5 rounded-full',
                                        aiEnabled ? 'bg-ai-green' : 'bg-status-revised',
                                    )}
                                />
                            ) : (
                                <Lock size={12} className="text-ink-faint" />
                            )}
                        </div>
                        <button
                            type="button"
                            onClick={onToggle}
                            className="flex size-6 items-center justify-center rounded text-ink-faint transition-colors hover:text-ink"
                        >
                            <CollapseIcon />
                        </button>
                    </div>

                    {licensed ? (
                        <>
                            {/* Scrollable content */}
                            <div className="flex flex-1 flex-col gap-5 overflow-y-auto p-4">
                                {/* Actions section */}
                                <div className="flex flex-col gap-2.5">
                                    <SectionLabel>Actions</SectionLabel>
                                    {aiEnabled ? (
                                        <>
                                            <button
                                                type="button"
                                                onClick={handleAnalyze}
                                                disabled={isAnalyzing}
                                                className={actionButtonClass}
                                            >
                                                <Table size={14} weight="bold" />
                                                {isAnalyzing ? 'Analyzing...' : 'Analyze chapter'}
                                            </button>
                                            <p className="text-xs leading-relaxed text-ink-muted">
                                                Runs chapter analysis, character extraction, and manuscript checks.
                                            </p>
                                            {analysisError && (
                                                <p className="text-xs leading-relaxed text-red-600">{analysisError}</p>
                                            )}
                                        </>
                                    ) : (
                                        <p className="text-xs leading-relaxed text-ink-muted">
                                            AI is not configured.{' '}
                                            <Link
                                                href="/settings/ai"
                                                className="font-medium text-accent underline decoration-accent/30 hover:decoration-accent"
                                            >
                                                Configure AI settings
                                            </Link>
                                        </p>
                                    )}
                                </div>

                                <SectionDivider />

                                {/* Prose section */}
                                <div className="flex flex-col gap-2.5">
                                    <SectionLabel>Prose</SectionLabel>
                                    {aiEnabled ? (
                                        <>
                                            <button
                                                type="button"
                                                onClick={handleRunProse}
                                                disabled={isRunningProse}
                                                className={actionButtonClass}
                                            >
                                                <Sparkle size={14} weight="fill" />
                                                {isRunningProse ? 'Running...' : 'Run prose pass'}
                                            </button>
                                            <p className="text-xs leading-relaxed text-ink-muted">
                                                Analyzes pacing, voice consistency, and prose quality.
                                            </p>
                                        </>
                                    ) : null}
                                </div>

                                <SectionDivider />

                                {/* Craft Metrics */}
                                <div className="flex flex-col gap-2.5">
                                    <SectionLabel>Craft Metrics</SectionLabel>
                                    <div className="flex flex-col gap-4">
                                        <LevelGroup title="Chapter">
                                            <CraftMetricRow
                                                label="Tension Dynamics"
                                                score={tensionLabel}
                                                detail={chapter.tension_score != null
                                                    ? `${chapter.tension_score}/10 · Micro-tension: ${chapter.micro_tension_score ?? '--'}`
                                                    : null}
                                            />
                                            <CraftMetricRow
                                                label="Emotional Arc"
                                                score={emotionalLabel}
                                                detail={chapter.emotional_shift_magnitude != null
                                                    ? `${chapter.emotional_state_open ?? '?'} → ${chapter.emotional_state_close ?? '?'} · Shift: ${chapter.emotional_shift_magnitude}`
                                                    : null}
                                            />
                                            <CraftMetricRow
                                                label="Pacing"
                                                score={pacingLabel}
                                                detail={chapter.pacing_feel
                                                    ? chapter.pacing_feel.charAt(0).toUpperCase() + chapter.pacing_feel.slice(1)
                                                    : null}
                                            />
                                        </LevelGroup>

                                        <LevelGroup title="Prose">
                                            <CraftMetricRow
                                                label="Craft Score"
                                                score={craftLabel}
                                                detail={chapter.sensory_grounding != null
                                                    ? `${chapter.sensory_grounding} senses · ${chapter.information_delivery ?? '--'}`
                                                    : null}
                                            />
                                            <CraftMetricRow
                                                label="Narrative Density"
                                                score={densityLabel}
                                                detail={densityDetail}
                                            />
                                        </LevelGroup>

                                        <LevelGroup title="Scene">
                                            <CraftMetricRow
                                                label="Scene Purpose"
                                                score={scenePurposeLabel}
                                                detail={chapter.scene_purpose
                                                    ? `${chapter.scene_purpose} · Value shift ${chapter.value_shift ? 'present' : 'absent'}`
                                                    : null}
                                            />
                                            <CraftMetricRow
                                                label="Hooks"
                                                score={hookLabel}
                                                detail={avgHookScore != null
                                                    ? `Entry: ${chapter.entry_hook_score ?? '--'} · Exit: ${chapter.exit_hook_score ?? '--'}${chapter.hook_type ? ` · ${chapter.hook_type.replace('_', ' ')}` : ''}`
                                                    : null}
                                            />
                                        </LevelGroup>

                                        <LevelGroup title="Manuscript">
                                            <CraftMetricRow
                                                label="Consistency"
                                                score={consistencyLabel}
                                                detail={consistencyDetail}
                                            />
                                            <CraftMetricRow
                                                label="Plot Alignment"
                                                score={plotLabel}
                                                detail={plotDetail}
                                            />
                                        </LevelGroup>
                                    </div>
                                </div>

                                <SectionDivider />

                                {/* Findings */}
                                <div className="flex flex-col gap-2.5">
                                    <SectionLabel>Findings</SectionLabel>
                                    {findings.length > 0 ? (
                                        <div className="flex flex-col gap-3">
                                            {findings.map((f, i) => (
                                                <div key={i} className="flex gap-2">
                                                    <FindingDot variant={f.variant} />
                                                    <span className="text-xs leading-relaxed text-ink-soft">{f.text}</span>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <p className="text-xs italic leading-relaxed text-ink-muted">
                                            {analysisStatus === 'completed' ? 'No findings' : 'No analysis run yet'}
                                        </p>
                                    )}
                                </div>

                                <SectionDivider />

                                {/* Next Chapter */}
                                <div className="flex flex-col gap-2.5">
                                    <SectionLabel>Next Chapter</SectionLabel>
                                    {nextSuggestion ? (
                                        <p className="text-[13px] leading-relaxed text-ink-soft">{nextSuggestion}</p>
                                    ) : (
                                        <p className="text-[13px] leading-relaxed text-ink-soft">
                                            Run an analysis first to receive chapter continuation suggestions.
                                        </p>
                                    )}
                                    <button
                                        type="button"
                                        disabled={!nextSuggestion}
                                        className="self-start text-xs font-medium text-accent transition-colors hover:text-accent/80 disabled:opacity-40"
                                    >
                                        Generate outline
                                    </button>
                                </div>

                                <SectionDivider />

                                {/* In This Chapter — Characters */}
                                <div className="flex flex-col gap-2.5">
                                    <SectionLabel>In This Chapter</SectionLabel>
                                    {characters.length > 0 ? (
                                        <div className="flex flex-col gap-2">
                                            {characters.map((character) => (
                                                <CharacterRow key={character.id} character={character} />
                                            ))}
                                        </div>
                                    ) : (
                                        <p className="text-xs italic leading-relaxed text-ink-muted">
                                            No characters linked
                                        </p>
                                    )}
                                </div>
                            </div>

                            {/* Bottom bar */}
                            <div className="flex items-center justify-between border-t border-border-subtle px-4 py-3">
                                <button
                                    type="button"
                                    onClick={onOpenChat}
                                    className="flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-xs font-medium text-ink-soft transition-colors hover:bg-surface hover:text-ink"
                                >
                                    <ChatCircle size={14} weight="regular" />
                                    Ask AI
                                </button>
                                <span className="text-[11px] text-ink-faint">~820 tokens</span>
                            </div>
                        </>
                    ) : (
                        <ProFeatureLock>
                            <div className="flex flex-1 flex-col gap-5 p-4 opacity-40">
                                <div className="flex flex-col gap-2.5">
                                    <SectionLabel>Actions</SectionLabel>
                                    <div className="h-9 rounded-md bg-border/50" />
                                </div>
                                <SectionDivider />
                                <div className="flex flex-col gap-2.5">
                                    <SectionLabel>Craft Metrics</SectionLabel>
                                    <div className="flex flex-col gap-4">
                                        <LevelGroup title="Chapter">
                                            <CraftMetricRow label="Tension Dynamics" score={EMPTY_SCORE} />
                                            <CraftMetricRow label="Emotional Arc" score={EMPTY_SCORE} />
                                            <CraftMetricRow label="Pacing" score={EMPTY_SCORE} />
                                        </LevelGroup>
                                        <LevelGroup title="Prose">
                                            <CraftMetricRow label="Craft Score" score={EMPTY_SCORE} />
                                            <CraftMetricRow label="Density" score={EMPTY_SCORE} />
                                        </LevelGroup>
                                    </div>
                                </div>
                            </div>
                        </ProFeatureLock>
                    )}
                </>
            ) : (
                /* Collapsed state */
                <button
                    type="button"
                    onClick={onToggle}
                    className="flex h-full w-full flex-col items-center gap-3 pt-3 transition-colors hover:bg-surface"
                >
                    <span className="flex size-6 items-center justify-center text-ink-faint">
                        <ExpandIcon />
                    </span>
                    {licensed ? (
                        <>
                            <span className="flex size-5 items-center justify-center text-ink-faint">
                                <SparkleIcon />
                            </span>
                            <span
                                className={cn(
                                    'size-1.5 rounded-full',
                                    aiEnabled ? 'bg-ai-green' : 'bg-status-revised',
                                )}
                            />
                        </>
                    ) : (
                        <Lock size={14} className="text-ink-faint" />
                    )}
                </button>
            )}
        </aside>
    );
}
