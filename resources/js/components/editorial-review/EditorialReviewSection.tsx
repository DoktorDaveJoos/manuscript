import { Check, ChevronRight, MessageCircle } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { show as chapterShow } from '@/actions/App/Http/Controllers/ChapterController';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import Checkbox from '@/components/ui/Checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/Collapsible';
import { severityDotColor } from '@/lib/editorial-constants';
import { cn } from '@/lib/utils';
import type {
    Chapter,
    EditorialReviewFinding,
    EditorialReviewSection as EditorialReviewSectionType,
    OnDiscussFinding,
} from '@/types/models';

function FindingItem({
    finding,
    chapters,
    bookId,
    isResolved,
    onToggleResolved,
    onDiscuss,
}: {
    finding: EditorialReviewFinding;
    chapters: Chapter[];
    bookId: number;
    isResolved: boolean;
    onToggleResolved: () => void;
    onDiscuss: () => void;
}) {
    const { t } = useTranslation('editorial-review');
    const [open, setOpen] = useState(false);

    const chapterRefs = finding.chapter_references
        .map((id) => {
            const chapter = chapters.find((c) => c.id === id);
            return chapter ? { id, label: chapter.reader_order + 1 } : null;
        })
        .filter(Boolean) as { id: number; label: number }[];

    const truncated =
        finding.description.length > 80
            ? finding.description.slice(0, 80) + '...'
            : finding.description;

    return (
        <div className={cn('flex flex-col py-2', isResolved && 'opacity-50')}>
            <div className="flex items-center gap-2">
                <Checkbox checked={isResolved} onChange={onToggleResolved} />

                <span
                    className={cn(
                        'size-2 shrink-0 rounded-full',
                        severityDotColor[finding.severity],
                    )}
                />

                <button
                    type="button"
                    onClick={() => setOpen(!open)}
                    className={cn(
                        'min-w-0 flex-1 text-left text-[13px] leading-snug text-ink',
                        isResolved && 'line-through',
                    )}
                >
                    {open ? finding.description : truncated}
                </button>

                {chapterRefs.length > 0 && (
                    <span className="flex shrink-0 items-center gap-1">
                        {chapterRefs.map((ref) => (
                            <a
                                key={ref.id}
                                href={chapterShow.url({
                                    book: bookId,
                                    chapter: ref.id,
                                })}
                                onClick={(e) => {
                                    e.stopPropagation();
                                }}
                                className="rounded bg-neutral-bg px-1.5 py-0.5 text-[11px] font-medium text-ink-muted transition-colors hover:bg-border-light hover:text-ink"
                            >
                                {t('section.chapters', {
                                    chapters: ref.label,
                                })}
                            </a>
                        ))}
                    </span>
                )}

                <button
                    type="button"
                    onClick={() => setOpen(!open)}
                    className="shrink-0 text-ink-faint"
                >
                    <ChevronRight
                        size={12}
                        className={cn(
                            'transition-transform duration-150',
                            open && 'rotate-90',
                        )}
                    />
                </button>
            </div>

            {open && (
                <div className="mt-2 flex flex-col gap-2 pl-6">
                    {finding.recommendation && (
                        <div className="rounded-md bg-neutral-bg px-3 py-2">
                            <p className="text-[11px] font-medium text-ink-faint">
                                {t('finding.recommendation')}
                            </p>
                            <p className="text-[13px] leading-relaxed text-ink-muted">
                                {finding.recommendation}
                            </p>
                        </div>
                    )}
                    <Button variant="secondary" size="sm" onClick={onDiscuss}>
                        <MessageCircle size={12} />
                        {t('section.discuss')}
                    </Button>
                </div>
            )}
        </div>
    );
}

export default function EditorialReviewSection({
    section,
    chapters,
    bookId,
    resolvedSet,
    onToggleFinding,
    onDiscussFinding,
}: {
    section: EditorialReviewSectionType;
    chapters: Chapter[];
    bookId: number;
    resolvedSet: Set<string>;
    onToggleFinding: (key: string) => void;
    onDiscussFinding: OnDiscussFinding;
}) {
    const { t } = useTranslation('editorial-review');
    const [open, setOpen] = useState(false);

    const findings = section.findings ?? [];
    const totalFindings = findings.length;
    const resolvedCount = findings.filter((f) => resolvedSet.has(f.key)).length;
    const remainingCount = totalFindings - resolvedCount;

    return (
        <Collapsible
            open={open}
            onOpenChange={setOpen}
            className="overflow-hidden rounded-lg border border-border-light bg-surface-card"
        >
            <CollapsibleTrigger className="flex w-full items-center gap-2 px-4 py-3 transition-colors hover:bg-neutral-bg">
                <ChevronRight
                    size={14}
                    className={cn(
                        'shrink-0 text-ink-faint transition-transform duration-200',
                        open && 'rotate-90',
                    )}
                />
                <span className="text-sm font-medium text-ink">
                    {t(`section.${section.type}`)}
                </span>

                {section.score !== null && (
                    <Badge variant="secondary" className="ml-auto">
                        {section.score} / 100
                    </Badge>
                )}

                {totalFindings > 0 && (
                    <span
                        className={cn(
                            'text-[11px] font-medium',
                            section.score === null && 'ml-auto',
                            remainingCount === 0
                                ? 'text-status-final'
                                : 'text-ink-faint',
                        )}
                    >
                        {remainingCount === 0 ? (
                            <span className="flex items-center gap-1">
                                <Check size={12} />
                                {t('section.allResolved')}
                            </span>
                        ) : (
                            t('section.remaining', {
                                count: remainingCount,
                            })
                        )}
                    </span>
                )}
            </CollapsibleTrigger>

            <CollapsibleContent className="overflow-hidden data-[state=closed]:animate-[collapsible-up_200ms_ease-out] data-[state=open]:animate-[collapsible-down_200ms_ease-out]">
                <div className="flex flex-col gap-2 px-4 pt-1 pb-4">
                    {section.summary && (
                        <p className="text-[13px] leading-relaxed text-ink-muted">
                            {section.summary}
                        </p>
                    )}

                    {totalFindings > 0 && (
                        <div className="flex flex-col divide-y divide-border-subtle">
                            {findings.map((finding, i) => (
                                <FindingItem
                                    key={finding.key}
                                    finding={finding}
                                    chapters={chapters}
                                    bookId={bookId}
                                    isResolved={resolvedSet.has(finding.key)}
                                    onToggleResolved={() =>
                                        onToggleFinding(finding.key)
                                    }
                                    onDiscuss={() =>
                                        onDiscussFinding(section.type, i, {
                                            description: finding.description,
                                            severity: finding.severity,
                                            sectionLabel: t(
                                                `section.${section.type}`,
                                            ),
                                        })
                                    }
                                />
                            ))}
                        </div>
                    )}
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}
