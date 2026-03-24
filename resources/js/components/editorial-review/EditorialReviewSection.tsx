import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/Collapsible';
import SectionLabel from '@/components/ui/SectionLabel';
import type {
    Chapter,
    EditorialReviewFinding,
    EditorialReviewSection as EditorialReviewSectionType,
    FindingSeverity,
} from '@/types/models';

const severityColor: Record<FindingSeverity, string> = {
    critical: 'bg-delete',
    warning: 'bg-accent',
    suggestion: 'bg-ink-faint',
};

const severityTextColor: Record<FindingSeverity, string> = {
    critical: 'text-delete',
    warning: 'text-accent',
    suggestion: 'text-ink-faint',
};

function ScoreBadge({ score }: { score: number | null }) {
    if (score === null) return null;

    return (
        <span className="ml-auto rounded-full bg-neutral-bg px-2 py-0.5 text-[11px] font-medium text-ink-muted">
            {score} / 100
        </span>
    );
}

function FindingItem({
    finding,
    chapters,
    onDiscuss,
}: {
    finding: EditorialReviewFinding;
    chapters: Chapter[];
    onDiscuss: () => void;
}) {
    const { t } = useTranslation('editorial-review');

    const chapterLabels = finding.chapter_references
        .map((id) => {
            const chapter = chapters.find((c) => c.id === id);
            return chapter ? chapter.reader_order + 1 : null;
        })
        .filter(Boolean);

    return (
        <div className="flex gap-3 py-2">
            <span
                className={`mt-[6px] size-2 shrink-0 rounded-full ${severityColor[finding.severity]}`}
            />
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <p className="text-[13px] leading-relaxed text-ink">
                    {finding.description}
                </p>
                <div className="flex flex-wrap items-center gap-2">
                    {chapterLabels.length > 0 && (
                        <span
                            className={`text-[11px] font-medium ${severityTextColor[finding.severity]}`}
                        >
                            {t('section.chapters', {
                                chapters: chapterLabels.join(', '),
                            })}
                        </span>
                    )}
                    <button
                        type="button"
                        onClick={onDiscuss}
                        className="text-[11px] font-medium text-accent transition-colors hover:text-accent-dark"
                    >
                        {t('section.discuss')}
                    </button>
                </div>
                {finding.recommendation && (
                    <p className="text-xs leading-relaxed text-ink-muted">
                        {finding.recommendation}
                    </p>
                )}
            </div>
        </div>
    );
}

export default function EditorialReviewSection({
    section,
    chapters,
    onDiscussFinding,
}: {
    section: EditorialReviewSectionType;
    chapters: Chapter[];
    onDiscussFinding: (sectionType: string, findingIndex: number) => void;
}) {
    const { t } = useTranslation('editorial-review');
    const [open, setOpen] = useState(false);

    return (
        <Collapsible open={open} onOpenChange={setOpen}>
            <CollapsibleTrigger className="flex w-full items-center gap-2 rounded-lg px-4 py-3 transition-colors hover:bg-neutral-bg">
                <ChevronRight
                    size={14}
                    className={`shrink-0 text-ink-faint transition-transform duration-200 ${open ? 'rotate-90' : ''}`}
                />
                <span className="text-sm font-medium text-ink">
                    {t(`section.${section.type}`)}
                </span>
                <ScoreBadge score={section.score} />
            </CollapsibleTrigger>

            <CollapsibleContent className="overflow-hidden data-[state=closed]:animate-[collapsible-up_200ms_ease-out] data-[state=open]:animate-[collapsible-down_200ms_ease-out]">
                <div className="flex flex-col gap-4 px-4 pt-1 pb-4">
                    {section.summary && (
                        <p className="text-[13px] leading-relaxed text-ink-muted">
                            {section.summary}
                        </p>
                    )}

                    {/* Findings */}
                    {section.findings && section.findings.length > 0 && (
                        <div className="flex flex-col gap-1">
                            <SectionLabel>{t('section.findings')}</SectionLabel>
                            <div className="flex flex-col divide-y divide-border-subtle">
                                {section.findings.map((finding, i) => (
                                    <FindingItem
                                        key={i}
                                        finding={finding}
                                        chapters={chapters}
                                        onDiscuss={() =>
                                            onDiscussFinding(section.type, i)
                                        }
                                    />
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Recommendations */}
                    {section.recommendations &&
                        section.recommendations.length > 0 && (
                            <div className="flex flex-col gap-2">
                                <SectionLabel>
                                    {t('section.recommendations')}
                                </SectionLabel>
                                <ol className="flex list-decimal flex-col gap-1.5 pl-5">
                                    {section.recommendations.map((rec, i) => (
                                        <li
                                            key={i}
                                            className="text-[13px] leading-relaxed text-ink-muted"
                                        >
                                            {rec}
                                        </li>
                                    ))}
                                </ol>
                            </div>
                        )}
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}
