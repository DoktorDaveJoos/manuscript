import {
    acceptPartialVersion,
    acceptVersion,
    rejectVersion,
} from '@/actions/App/Http/Controllers/ChapterController';
import { getXsrfToken } from '@/lib/csrf';
import { ruleCheckers, RULE_THRESHOLDS, stripTags } from '@/lib/ruleCheckers';
import type { ChapterVersion, ProsePassRule, VersionSource } from '@/types/models';
import { CaretDown, CaretRight, Check, Warning } from '@phosphor-icons/react';
import { router } from '@inertiajs/react';
import { diffArrays, diffWords } from 'diff';
import { useCallback, useEffect, useMemo, useState } from 'react';

const sourceLabel: Record<VersionSource, string> = {
    original: 'original',
    ai_revision: 'ai prose pass',
    manual_edit: 'manual edit',
    normalization: 'normalize',
    beautify: 'beautify',
    snapshot: 'snapshot',
};

function splitParagraphs(html: string | null): string[] {
    if (!html) return [];
    return html
        .replace(/<\/p>\s*<p>/gi, '</p>\n<p>')
        .split(/\n/)
        .map((p) => p.trim())
        .filter(Boolean);
}

type DiffSegment = { text: string; type: 'equal' | 'added' | 'removed' };

type AlignedParagraph = {
    left: { segments: DiffSegment[] } | null;
    right: { segments: DiffSegment[] } | null;
    hasChanges: boolean;
    index: number;
};

function computeDiff(
    originalHtml: string | null,
    revisedHtml: string | null,
): {
    aligned: AlignedParagraph[];
    changeCount: number;
    changedIndices: number[];
} {
    const origParagraphs = splitParagraphs(originalHtml);
    const revParagraphs = splitParagraphs(revisedHtml);

    const origTexts = origParagraphs.map(stripTags);
    const revTexts = revParagraphs.map(stripTags);

    // Use diffArrays to align paragraphs
    const arrayDiff = diffArrays(origTexts, revTexts);

    const aligned: AlignedParagraph[] = [];
    let origIdx = 0;
    let revIdx = 0;
    let changeCount = 0;
    const changedIndices: number[] = [];

    for (const part of arrayDiff) {
        if (!part.added && !part.removed) {
            // Equal paragraphs — compute word-level diff within each pair
            for (let i = 0; i < part.count!; i++) {
                const wordDiffs = diffWords(origTexts[origIdx], revTexts[revIdx]);
                const leftSegs: DiffSegment[] = [];
                const rightSegs: DiffSegment[] = [];
                let pairHasChanges = false;

                for (const w of wordDiffs) {
                    if (w.added) {
                        rightSegs.push({ text: w.value, type: 'added' });
                        pairHasChanges = true;
                        changeCount++;
                    } else if (w.removed) {
                        leftSegs.push({ text: w.value, type: 'removed' });
                        pairHasChanges = true;
                    } else {
                        leftSegs.push({ text: w.value, type: 'equal' });
                        rightSegs.push({ text: w.value, type: 'equal' });
                    }
                }

                const idx = aligned.length;
                if (pairHasChanges) changedIndices.push(idx);
                aligned.push({
                    left: { segments: leftSegs },
                    right: { segments: rightSegs },
                    hasChanges: pairHasChanges,
                    index: idx,
                });

                origIdx++;
                revIdx++;
            }
        } else if (part.removed && !part.added) {
            for (let i = 0; i < part.count!; i++) {
                const idx = aligned.length;
                changedIndices.push(idx);
                changeCount++;
                aligned.push({
                    left: { segments: [{ text: origTexts[origIdx], type: 'removed' }] },
                    right: null,
                    hasChanges: true,
                    index: idx,
                });
                origIdx++;
            }
        } else if (part.added && !part.removed) {
            for (let i = 0; i < part.count!; i++) {
                const idx = aligned.length;
                changedIndices.push(idx);
                changeCount++;
                aligned.push({
                    left: null,
                    right: { segments: [{ text: revTexts[revIdx], type: 'added' }] },
                    hasChanges: true,
                    index: idx,
                });
                revIdx++;
            }
        }
    }

    return { aligned, changeCount, changedIndices };
}

function mergeParagraphs(
    aligned: AlignedParagraph[],
    selectedIndices: Set<number>,
    originalHtml: string | null,
    revisedHtml: string | null,
): string {
    const origParagraphs = splitParagraphs(originalHtml);
    const revParagraphs = splitParagraphs(revisedHtml);

    const origTexts = origParagraphs.map(stripTags);
    const revTexts = revParagraphs.map(stripTags);

    // Build lookup maps from text to original HTML paragraph
    const origMap = new Map<string, string[]>();
    origTexts.forEach((t, i) => {
        if (!origMap.has(t)) origMap.set(t, []);
        origMap.get(t)!.push(origParagraphs[i]);
    });
    const revMap = new Map<string, string[]>();
    revTexts.forEach((t, i) => {
        if (!revMap.has(t)) revMap.set(t, []);
        revMap.get(t)!.push(revParagraphs[i]);
    });

    const origUsed = new Map<string, number>();
    const revUsed = new Map<string, number>();

    function makeHtmlLookup(map: Map<string, string[]>, used: Map<string, number>) {
        return (text: string): string => {
            const idx = used.get(text) ?? 0;
            const arr = map.get(text);
            used.set(text, idx + 1);
            return arr?.[idx] ?? `<p>${text}</p>`;
        };
    }

    const getOrigHtml = makeHtmlLookup(origMap, origUsed);
    const getRevHtml = makeHtmlLookup(revMap, revUsed);

    const result: string[] = [];

    for (const para of aligned) {
        const useRevised = selectedIndices.has(para.index);

        if (!para.hasChanges) {
            // Unchanged paragraph — include from original
            const text = para.left?.segments.map((s) => s.text).join('') ?? '';
            result.push(getOrigHtml(text));
        } else if (useRevised) {
            // Changed paragraph, user selected revised
            if (para.right) {
                const text = para.right.segments.map((s) => s.text).join('');
                result.push(getRevHtml(text));
            }
            // If right is null (deleted paragraph), omit it
        } else {
            // Changed paragraph, user kept original
            if (para.left) {
                const text = para.left.segments.map((s) => s.text).join('');
                result.push(getOrigHtml(text));
            }
            // If left is null (added paragraph), omit it
        }
    }

    return result.join('\n');
}

export default function DiffView({
    bookId,
    chapterId,
    chapterTitle,
    currentVersion,
    pendingVersion,
    prosePassRules,
}: {
    bookId: number;
    chapterId: number;
    chapterTitle: string;
    currentVersion: ChapterVersion;
    pendingVersion: ChapterVersion;
    prosePassRules?: ProsePassRule[];
}) {
    const [isAccepting, setIsAccepting] = useState(false);
    const [isRejecting, setIsRejecting] = useState(false);
    const [rulesExpanded, setRulesExpanded] = useState(false);

    const diff = useMemo(
        () => computeDiff(currentVersion.content, pendingVersion.content),
        [currentVersion.content, pendingVersion.content],
    );

    const [selectedParagraphs, setSelectedParagraphs] = useState<Set<number>>(
        () => new Set(diff.changedIndices),
    );

    useEffect(() => {
        setSelectedParagraphs(new Set(diff.changedIndices));
    }, [diff.changedIndices]);

    const totalChanged = diff.changedIndices.length;
    const selectedCount = diff.changedIndices.filter((i) => selectedParagraphs.has(i)).length;
    const isPartial = selectedCount < totalChanged;

    const toggleParagraph = useCallback((index: number) => {
        setSelectedParagraphs((prev) => {
            const next = new Set(prev);
            if (next.has(index)) {
                next.delete(index);
            } else {
                next.add(index);
            }
            return next;
        });
    }, []);

    const toggleAll = useCallback(() => {
        if (selectedCount === totalChanged) {
            setSelectedParagraphs(new Set());
        } else {
            setSelectedParagraphs(new Set(diff.changedIndices));
        }
    }, [selectedCount, totalChanged, diff.changedIndices]);

    // Truncation detection
    const truncationWarning = useMemo(() => {
        const origWords = stripTags(currentVersion.content ?? '')
            .split(/\s+/)
            .filter(Boolean).length;
        const revWords = stripTags(pendingVersion.content ?? '')
            .split(/\s+/)
            .filter(Boolean).length;
        if (origWords > 0 && revWords < origWords * 0.7) {
            return { origWords, revWords };
        }
        return null;
    }, [currentVersion.content, pendingVersion.content]);

    // Rule compliance
    const ruleResults = useMemo(() => {
        if (!prosePassRules) return [];
        const enabledRules = prosePassRules.filter((r) => r.enabled);
        if (enabledRules.length === 0) return [];

        const revisedText = pendingVersion.content ?? '';
        return enabledRules
            .map((rule) => {
                const checker = ruleCheckers[rule.key];
                if (!checker) return null;
                const result = checker(revisedText);
                const threshold = RULE_THRESHOLDS[rule.key] ?? 5;
                return {
                    label: rule.label,
                    key: rule.key,
                    pass: result.count < threshold,
                    count: result.count,
                    examples: result.examples,
                };
            })
            .filter(Boolean) as { label: string; key: string; pass: boolean; count: number; examples: string[] }[];
    }, [prosePassRules, pendingVersion.content]);

    const handleAccept = useCallback(async () => {
        setIsAccepting(true);
        try {
            if (isPartial) {
                const mergedContent = mergeParagraphs(
                    diff.aligned,
                    selectedParagraphs,
                    currentVersion.content,
                    pendingVersion.content,
                );
                const response = await fetch(
                    acceptPartialVersion.url({
                        book: bookId,
                        chapter: chapterId,
                        version: pendingVersion.id,
                    }),
                    {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-XSRF-TOKEN': getXsrfToken(),
                        },
                        body: JSON.stringify({ content: mergedContent }),
                    },
                );
                if (!response.ok) throw new Error('Accept failed');
            } else {
                const response = await fetch(
                    acceptVersion.url({
                        book: bookId,
                        chapter: chapterId,
                        version: pendingVersion.id,
                    }),
                    {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-XSRF-TOKEN': getXsrfToken(),
                        },
                    },
                );
                if (!response.ok) throw new Error('Accept failed');
            }
            router.reload();
        } catch {
            setIsAccepting(false);
        }
    }, [bookId, chapterId, pendingVersion.id, pendingVersion.content, currentVersion.content, isPartial, selectedParagraphs, diff.aligned]);

    const handleReject = useCallback(async () => {
        setIsRejecting(true);
        try {
            const response = await fetch(
                rejectVersion.url({
                    book: bookId,
                    chapter: chapterId,
                    version: pendingVersion.id,
                }),
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': getXsrfToken(),
                    },
                },
            );
            if (!response.ok) throw new Error('Reject failed');
            router.reload();
        } catch {
            setIsRejecting(false);
        }
    }, [bookId, chapterId, pendingVersion.id]);

    const acceptLabel = isPartial
        ? `Accept ${selectedCount} of ${totalChanged} changes`
        : 'Accept all';

    return (
        <div className="flex flex-1 flex-col overflow-hidden">
            {/* Review bar */}
            <div className="flex h-12 shrink-0 items-center justify-between border-b border-border px-6">
                <div className="flex items-center gap-2 text-sm">
                    <span className="text-ink-faint">{chapterTitle}</span>
                    <span className="text-ink-faint">/</span>
                    <span className="font-medium text-accent">
                        Reviewing {sourceLabel[pendingVersion.source] ?? pendingVersion.source}
                    </span>
                    <span className="text-ink-faint">
                        {diff.changeCount} {diff.changeCount === 1 ? 'change' : 'changes'}
                    </span>
                    {totalChanged > 0 && (
                        <button
                            type="button"
                            onClick={toggleAll}
                            className="ml-2 text-xs font-medium text-accent transition-colors hover:text-accent/80"
                        >
                            {selectedCount === totalChanged ? 'Deselect all' : 'Select all'}
                        </button>
                    )}
                </div>
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={handleReject}
                        disabled={isRejecting || isAccepting}
                        className="rounded-md border border-border px-3 py-1.5 text-xs font-medium text-ink-muted transition-colors hover:bg-neutral-bg hover:text-ink disabled:opacity-50"
                    >
                        {isRejecting ? 'Rejecting...' : 'Reject all'}
                    </button>
                    <button
                        type="button"
                        onClick={handleAccept}
                        disabled={isAccepting || isRejecting || selectedCount === 0}
                        className="rounded-md bg-ink px-3 py-1.5 text-xs font-medium text-surface transition-colors hover:bg-ink/90 disabled:opacity-50"
                    >
                        {isAccepting ? 'Accepting...' : acceptLabel}
                    </button>
                </div>
            </div>

            {/* Truncation warning */}
            {truncationWarning && (
                <div className="flex items-center gap-2 border-b border-amber-200 bg-amber-50 px-6 py-2.5">
                    <Warning size={16} weight="fill" className="shrink-0 text-amber-600" />
                    <p className="text-xs leading-relaxed text-amber-800">
                        The revised text is significantly shorter than the original (
                        {truncationWarning.revWords.toLocaleString()} words vs{' '}
                        {truncationWarning.origWords.toLocaleString()} words). The AI response may
                        have been truncated. Consider rejecting and re-running on a shorter chapter.
                    </p>
                </div>
            )}

            {/* Side-by-side diff */}
            <div className="flex flex-1 overflow-hidden">
                {/* Left: Original */}
                <div className="flex-1 overflow-y-auto border-r border-border px-12 py-8">
                    <div className="mb-6 flex items-center gap-2">
                        <span className="text-xs font-semibold uppercase tracking-wider text-ink-faint">
                            Original
                        </span>
                        <span className="text-xs text-ink-faint">
                            v{currentVersion.version_number} &middot;{' '}
                            {sourceLabel[currentVersion.source] ?? currentVersion.source}
                        </span>
                    </div>
                    <div className="max-w-prose space-y-4 font-serif text-base leading-relaxed text-ink">
                        {diff.aligned.map((para) =>
                            para.left ? (
                                <p key={`l-${para.index}`} className={!para.right ? 'opacity-60' : undefined}>
                                    {para.left.segments.map((seg, j) =>
                                        seg.type === 'removed' ? (
                                            <span
                                                key={j}
                                                className="bg-delete-bg text-delete line-through"
                                            >
                                                {seg.text}
                                            </span>
                                        ) : (
                                            <span key={j}>{seg.text}</span>
                                        ),
                                    )}
                                </p>
                            ) : (
                                <p
                                    key={`l-${para.index}`}
                                    className="h-0"
                                    aria-hidden="true"
                                />
                            ),
                        )}
                    </div>
                </div>

                {/* Right: Revision */}
                <div className="flex-1 overflow-y-auto px-12 py-8">
                    <div className="mb-6 flex items-center gap-2">
                        <span className="text-xs font-semibold uppercase tracking-wider text-ink-faint">
                            Revision
                        </span>
                        <span className="text-xs text-ink-faint">
                            v{pendingVersion.version_number} &middot;{' '}
                            {sourceLabel[pendingVersion.source] ?? pendingVersion.source}
                        </span>
                    </div>
                    <div className="max-w-prose space-y-4 font-serif text-base leading-relaxed text-ink">
                        {diff.aligned.map((para) =>
                            para.right ? (
                                <div key={`r-${para.index}`} className="group flex gap-2">
                                    {para.hasChanges && (
                                        <button
                                            type="button"
                                            onClick={() => toggleParagraph(para.index)}
                                            className="mt-1 flex size-4 shrink-0 items-center justify-center rounded border border-border transition-colors hover:border-accent"
                                        >
                                            {selectedParagraphs.has(para.index) && (
                                                <Check
                                                    size={10}
                                                    weight="bold"
                                                    className="text-accent"
                                                />
                                            )}
                                        </button>
                                    )}
                                    <p className={!para.left ? 'opacity-80' : undefined}>
                                        {para.right.segments.map((seg, j) =>
                                            seg.type === 'added' ? (
                                                <span
                                                    key={j}
                                                    className="bg-ai-green/15 text-ai-green"
                                                >
                                                    {seg.text}
                                                </span>
                                            ) : (
                                                <span key={j}>{seg.text}</span>
                                            ),
                                        )}
                                    </p>
                                </div>
                            ) : (
                                <p
                                    key={`r-${para.index}`}
                                    className="h-0"
                                    aria-hidden="true"
                                />
                            ),
                        )}
                    </div>
                </div>
            </div>

            {/* Rule compliance panel */}
            {ruleResults.length > 0 && (
                <div className="shrink-0 border-t border-border">
                    <button
                        type="button"
                        onClick={() => setRulesExpanded(!rulesExpanded)}
                        className="flex w-full items-center gap-2 px-6 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-ink-faint transition-colors hover:text-ink-muted"
                    >
                        {rulesExpanded ? <CaretDown size={12} /> : <CaretRight size={12} />}
                        Rule compliance
                        <span className="font-normal normal-case tracking-normal">
                            {ruleResults.filter((r) => r.pass).length}/{ruleResults.length} passing
                        </span>
                    </button>
                    {rulesExpanded && (
                        <div className="grid grid-cols-2 gap-x-8 gap-y-2 px-6 pb-4 lg:grid-cols-3">
                            {ruleResults.map((rule) => (
                                <div key={rule.key} className="flex items-start gap-2">
                                    <span
                                        className={`mt-0.5 size-2 shrink-0 rounded-full ${rule.pass ? 'bg-ai-green' : 'bg-amber-500'}`}
                                    />
                                    <div className="min-w-0">
                                        <span className="text-xs font-medium text-ink-soft">
                                            {rule.label}
                                        </span>
                                        <span className="ml-1.5 text-xs text-ink-faint">
                                            {rule.count} found
                                        </span>
                                        {!rule.pass && rule.examples.length > 0 && (
                                            <p className="truncate text-[11px] text-ink-faint">
                                                {rule.examples.join(', ')}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
