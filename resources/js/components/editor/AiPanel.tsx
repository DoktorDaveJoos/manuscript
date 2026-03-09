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

function scoreLabel(score: number | null): { text: string; color: string } {
    if (score === null) return { text: '--', color: 'text-ink-faint' };
    if (score >= 7) return { text: 'Good', color: 'text-ai-green' };
    if (score >= 4) return { text: 'Fair', color: 'text-status-revised' };
    return { text: 'Weak', color: 'text-red-600' };
}

function MetricRow({ label, value, color }: { label: string; value: string; color?: string }) {
    return (
        <div className="flex items-center justify-between">
            <span className="text-[13px] text-ink-soft">{label}</span>
            <span className={cn('text-xs font-medium', color ?? 'text-ink-faint')}>{value}</span>
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
    }, [book.id, chapter.id, onError]);

    const tensionLabel = scoreLabel(chapter.tension_score);
    const hookLabel = scoreLabel(chapter.hook_score);
    const findings = useMemo(() => collectFindings(analyses), [analyses]);
    const nextSuggestion = useMemo(() => getNextChapterSuggestion(analyses), [analyses]);

    // Derive pacing & density labels from analyses
    const pacingAnalysis = analyses['pacing']?.result as { score?: number } | null;
    const densityAnalysis = analyses['density']?.result as { score?: number } | null;
    const pacingLabel = scoreLabel(pacingAnalysis?.score ?? null);
    const densityLabel = scoreLabel(densityAnalysis?.score ?? null);

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

                                {/* Chapter Analysis */}
                                <div className="flex flex-col gap-2.5">
                                    <SectionLabel>Chapter Analysis</SectionLabel>
                                    <div className="flex flex-col gap-1.5">
                                        <MetricRow label="Tension" value={tensionLabel.text} color={tensionLabel.color} />
                                        <MetricRow label="Hook strength" value={hookLabel.text} color={hookLabel.color} />
                                        <MetricRow label="Pacing" value={pacingLabel.text} color={pacingLabel.color} />
                                        <MetricRow label="Density" value={densityLabel.text} color={densityLabel.color} />
                                    </div>
                                    {chapter.hook_type && (
                                        <span className="text-[11px] text-ink-faint">
                                            Hook type: {chapter.hook_type.replace('_', ' ')}
                                        </span>
                                    )}
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
                                    <SectionLabel>Chapter Analysis</SectionLabel>
                                    <div className="flex flex-col gap-1.5">
                                        <MetricRow label="Tension" value="--" />
                                        <MetricRow label="Hook strength" value="--" />
                                        <MetricRow label="Pacing" value="--" />
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
