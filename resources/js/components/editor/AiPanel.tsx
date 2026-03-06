import { revise } from '@/actions/App/Http/Controllers/AiController';
import ProFeatureLock from '@/components/ui/ProFeatureLock';
import { getXsrfToken } from '@/lib/csrf';
import { cn } from '@/lib/utils';
import type { Book, Chapter, Character, CharacterChapterPivot, CharacterRole } from '@/types/models';
import { CaretLeft, CaretRight, Lock, PaperPlaneTilt, Sparkle } from '@phosphor-icons/react';
import { useState } from 'react';

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

function SendIcon() {
    return <PaperPlaneTilt size={14} weight="fill" />;
}

export default function AiPanel({
    characters,
    book,
    chapter,
    isOpen,
    onToggle,
    licensed = true,
}: {
    characters: ChapterCharacter[];
    book: Book;
    chapter: Chapter;
    isOpen: boolean;
    onToggle: () => void;
    licensed?: boolean;
}) {
    const [isRunningProse, setIsRunningProse] = useState(false);

    const handleRunProse = async () => {
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
        } catch {
            // Silently handle — user will see no change
        } finally {
            setIsRunningProse(false);
        }
    };

    return (
        <aside
            className={cn(
                'flex h-full shrink-0 flex-col border-l border-border bg-surface transition-[width] duration-200 ease-in-out',
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
                                <span className="size-1.5 rounded-full bg-ai-green" />
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
                                {/* Prose section */}
                                <div className="flex flex-col gap-2.5">
                                    <SectionLabel>Prose</SectionLabel>
                                    <button
                                        type="button"
                                        onClick={handleRunProse}
                                        disabled={isRunningProse}
                                        className="rounded bg-ink px-3 py-1.5 text-[13px] font-medium text-white transition-colors hover:bg-ink/90 disabled:opacity-50"
                                    >
                                        {isRunningProse ? 'Running...' : 'Run prose pass'}
                                    </button>
                                    <p className="text-xs leading-relaxed text-ink-muted">
                                        Analyzes pacing, voice consistency, and prose quality.
                                    </p>
                                </div>

                                <SectionDivider />

                                {/* Chapter Analysis */}
                                <div className="flex flex-col gap-2.5">
                                    <SectionLabel>Chapter Analysis</SectionLabel>
                                    <div className="flex flex-col gap-1.5">
                                        <MetricRow label="Pacing score" value="--" color="text-ai-green" />
                                        <MetricRow label="Readability" value="--" color="text-ai-green" />
                                        <MetricRow label="Dialogue ratio" value="--" color="text-status-revised" />
                                        <MetricRow label="Tension arc" value="--" color="text-ai-green" />
                                    </div>
                                </div>

                                <SectionDivider />

                                {/* Findings */}
                                <div className="flex flex-col gap-2.5">
                                    <SectionLabel>Findings</SectionLabel>
                                    <p className="text-xs italic leading-relaxed text-ink-muted">
                                        No analysis run yet
                                    </p>
                                </div>

                                <SectionDivider />

                                {/* Next Chapter */}
                                <div className="flex flex-col gap-2.5">
                                    <SectionLabel>Next Chapter</SectionLabel>
                                    <p className="text-[13px] leading-relaxed text-ink-soft">
                                        Run an analysis first to receive chapter continuation suggestions.
                                    </p>
                                    <button
                                        type="button"
                                        disabled
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

                            {/* Chat Input */}
                            <div className="px-4 pb-3">
                                <div className="flex items-center gap-2">
                                    <input
                                        type="text"
                                        placeholder="Ask about this chapter..."
                                        disabled
                                        className="h-10 flex-1 rounded-md border border-border bg-surface px-3 text-[13px] text-ink placeholder:text-ink-faint disabled:opacity-60"
                                    />
                                    <button
                                        type="button"
                                        disabled
                                        className="flex size-8 shrink-0 items-center justify-center rounded-md border border-border text-ink-faint transition-colors hover:text-ink disabled:opacity-40"
                                    >
                                        <SendIcon />
                                    </button>
                                </div>
                            </div>

                            {/* Token Footer */}
                            <div className="border-t border-border-subtle px-5 py-3.5">
                                <span className="text-[11px] text-ink-faint">~820 tokens ~ $0.003</span>
                            </div>
                        </>
                    ) : (
                        <ProFeatureLock>
                            <div className="flex flex-1 flex-col gap-5 p-4 opacity-40">
                                <div className="flex flex-col gap-2.5">
                                    <SectionLabel>Prose</SectionLabel>
                                    <div className="h-8 rounded bg-border/50" />
                                </div>
                                <SectionDivider />
                                <div className="flex flex-col gap-2.5">
                                    <SectionLabel>Chapter Analysis</SectionLabel>
                                    <div className="flex flex-col gap-1.5">
                                        <MetricRow label="Pacing score" value="--" />
                                        <MetricRow label="Readability" value="--" />
                                        <MetricRow label="Dialogue ratio" value="--" />
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
                            <span className="size-1.5 rounded-full bg-ai-green" />
                        </>
                    ) : (
                        <Lock size={14} className="text-ink-faint" />
                    )}
                </button>
            )}
        </aside>
    );
}
