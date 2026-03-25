import { ChevronRight, MessageCircle } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/Collapsible';
import SectionLabel from '@/components/ui/SectionLabel';
import {
    severityBadgeVariant,
    severityTextColor,
} from '@/lib/editorial-constants';
import type {
    Chapter,
    EditorialReviewFinding,
    EditorialReviewSection as EditorialReviewSectionType,
    OnDiscussFinding,
} from '@/types/models';

function ScoreBadge({ score }: { score: number | null }) {
    if (score === null) return null;

    return (
        <Badge variant="secondary" className="ml-auto">
            {score} / 100
        </Badge>
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
        <div className="flex flex-col gap-1 py-2">
            <div className="flex items-center gap-2">
                <Badge variant={severityBadgeVariant[finding.severity]}>
                    {t(`severity.${finding.severity}`)}
                </Badge>
                {chapterLabels.length > 0 && (
                    <span
                        className={`text-[11px] font-medium ${severityTextColor[finding.severity]}`}
                    >
                        {t('section.chapters', {
                            chapters: chapterLabels.join(', '),
                        })}
                    </span>
                )}
            </div>
            <p className="text-[13px] leading-relaxed text-ink">
                {finding.description}
            </p>
            <Button variant="secondary" size="sm" onClick={onDiscuss}>
                <MessageCircle size={12} />
                {t('section.discuss')}
            </Button>
            {finding.recommendation && (
                <p className="text-xs leading-relaxed text-ink-muted">
                    {finding.recommendation}
                </p>
            )}
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
    onDiscussFinding: OnDiscussFinding;
}) {
    const { t } = useTranslation('editorial-review');
    const [open, setOpen] = useState(false);

    return (
        <Collapsible
            open={open}
            onOpenChange={setOpen}
            className="overflow-hidden rounded-lg border border-border-light bg-surface-card"
        >
            <CollapsibleTrigger className="flex w-full items-center gap-2 px-4 py-3 transition-colors hover:bg-neutral-bg">
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
                                            onDiscussFinding(section.type, i, {
                                                description:
                                                    finding.description,
                                                severity: finding.severity,
                                                sectionLabel: t(
                                                    `section.${section.type}`,
                                                ),
                                            })
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
