import { Check, PencilLine } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import Dialog from '@/components/ui/Dialog';
import SectionLabel from '@/components/ui/SectionLabel';
import Select from '@/components/ui/Select';
import { useToggleFinding } from '@/hooks/useToggleFinding';
import { qualityBarColor, scoreQuality } from '@/lib/editorial-constants';
import type { ScoreQuality } from '@/lib/editorial-constants';
import { cn } from '@/lib/utils';
import type {
    Chapter,
    EditorialReview,
    EditorialReviewSection as EditorialReviewSectionModel,
    EditorialSectionType,
    OnDiscussFinding,
} from '@/types/models';
import EditorialReviewSection from './EditorialReviewSection';

function BulletList({
    items,
    dotColor,
}: {
    items: string[];
    dotColor: string;
}) {
    return (
        <div className="flex flex-col gap-2">
            {items.map((item, i) => (
                <div key={i} className="flex items-start gap-2">
                    <span
                        className={`mt-[6px] size-2 shrink-0 rounded-full ${dotColor}`}
                    />
                    <span className="text-[13px] leading-relaxed text-ink-muted">
                        {item}
                    </span>
                </div>
            ))}
        </div>
    );
}

function ScoreDisplay({
    score,
    qualityLabel,
}: {
    score: number;
    qualityLabel: Record<ScoreQuality, string>;
}) {
    return (
        <div className="flex flex-col items-center gap-1 rounded-lg bg-neutral-bg px-5 py-3">
            <span className="font-serif text-[32px] leading-[1] font-semibold text-ink">
                {score}
            </span>
            <span className="text-[11px] font-medium text-ink-faint">
                {qualityLabel[scoreQuality(score)]}
            </span>
        </div>
    );
}

function StrengthsAndImprovements({
    strengths,
    improvements,
}: {
    strengths: string[];
    improvements: string[];
}) {
    const { t } = useTranslation('editorial-review');

    return (
        <div className="flex gap-8 border-t border-border-subtle pt-6">
            {strengths.length > 0 && (
                <div className="flex flex-1 flex-col gap-2">
                    <SectionLabel>{t('report.strengths')}</SectionLabel>
                    <BulletList items={strengths} dotColor="bg-status-final" />
                </div>
            )}

            <div className="flex flex-1 flex-col gap-2">
                <SectionLabel>{t('report.improvements')}</SectionLabel>
                <BulletList items={improvements} dotColor="bg-accent" />
            </div>
        </div>
    );
}

function DimensionTile({
    label,
    section,
    openCount,
    onClick,
}: {
    label: string;
    section: EditorialReviewSectionModel;
    openCount: number;
    onClick: () => void;
}) {
    const { t } = useTranslation('editorial-review');
    const totalFindings = (section.findings ?? []).length;

    return (
        <button
            type="button"
            onClick={onClick}
            className="flex flex-col gap-3 rounded-xl border border-border-light bg-surface-card p-4 text-left transition-colors hover:border-border"
        >
            <span className="text-xs font-medium text-ink-muted">{label}</span>

            <span className="text-2xl leading-none font-semibold tracking-[-0.01em] text-ink">
                {section.score ?? '—'}
            </span>

            <div className="mt-auto flex w-full flex-col gap-2">
                <div className="h-1 w-full overflow-hidden rounded bg-neutral-bg">
                    {section.score !== null && (
                        <div
                            className={cn(
                                'h-full rounded',
                                qualityBarColor[scoreQuality(section.score)],
                            )}
                            style={{ width: `${section.score}%` }}
                        />
                    )}
                </div>

                {totalFindings > 0 &&
                    (openCount === 0 ? (
                        <span className="flex items-center gap-1 text-[11px] font-medium text-status-final">
                            <Check size={12} />
                            {t('section.allResolved')}
                        </span>
                    ) : (
                        <span className="text-[11px] font-medium text-ink-faint">
                            {t('section.remaining', { count: openCount })}
                        </span>
                    ))}
            </div>
        </button>
    );
}

export default function EditorialReviewReport({
    review,
    reviews,
    chapters,
    editedChaptersCount,
    onSelectReview,
    onStartNew,
    starting,
    onDiscussFinding,
    onResolvedChange,
}: {
    review: EditorialReview;
    reviews: EditorialReview[];
    chapters: Chapter[];
    editedChaptersCount: number | null;
    onSelectReview: (review: EditorialReview) => void;
    onStartNew: () => void;
    starting: boolean;
    onDiscussFinding: OnDiscussFinding;
    onResolvedChange: (resolved: string[]) => void;
}) {
    const { t, i18n } = useTranslation('editorial-review');
    const [showConfirm, setShowConfirm] = useState(false);
    const [openSections, setOpenSections] = useState<Set<EditorialSectionType>>(
        () => new Set(),
    );
    const sectionRefs = useRef<
        Partial<Record<EditorialSectionType, HTMLDivElement | null>>
    >({});

    const resolvedFindings = review.resolved_findings ?? [];
    const resolvedSet = useMemo(
        () => new Set(resolvedFindings),
        [resolvedFindings],
    );

    const handleToggleFinding = useToggleFinding(
        review.book_id,
        review.id,
        resolvedFindings,
        onResolvedChange,
    );

    const formatDate = (dateStr: string | null) =>
        dateStr
            ? new Date(dateStr).toLocaleDateString(i18n.language, {
                  year: 'numeric',
                  month: 'short',
                  day: 'numeric',
              })
            : '';

    const sectionOrder = [
        'plot',
        'characters',
        'pacing',
        'narrative_voice',
        'themes',
        'scene_craft',
        'prose_style',
        'chapter_notes',
    ] as const;

    const orderedSections = sectionOrder
        .map((type) => review.sections.find((s) => s.type === type))
        .filter(Boolean) as EditorialReviewSectionModel[];

    const openFindingsCount = (section: EditorialReviewSectionModel) =>
        (section.findings ?? []).filter((f) => !resolvedSet.has(f.key)).length;

    const setSectionOpen = (type: EditorialSectionType, open: boolean) => {
        setOpenSections((prev) => {
            const next = new Set(prev);
            if (open) {
                next.add(type);
            } else {
                next.delete(type);
            }
            return next;
        });
    };

    const openAndScrollTo = (type: EditorialSectionType) => {
        setSectionOpen(type, true);
        requestAnimationFrame(() => {
            sectionRefs.current[type]?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        });
    };

    return (
        <>
            <div className="flex flex-col gap-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Select
                        variant="compact"
                        value={review.id}
                        onChange={(e) => {
                            const selected = reviews.find(
                                (r) => r.id === Number(e.target.value),
                            );
                            if (selected) onSelectReview(selected);
                        }}
                    >
                        {reviews.map((r) => (
                            <option key={r.id} value={r.id}>
                                {t('report.reviewFrom', {
                                    date:
                                        formatDate(r.completed_at) ||
                                        `#${r.id}`,
                                })}
                            </option>
                        ))}
                    </Select>

                    <div className="flex items-center gap-4">
                        {(editedChaptersCount ?? 0) > 0 && (
                            <span className="flex items-center gap-1.5 text-xs text-ink-muted">
                                <PencilLine
                                    size={12}
                                    className="shrink-0 text-ink-faint"
                                />
                                {t('report.editedChaptersHint', {
                                    count: editedChaptersCount ?? 0,
                                })}
                            </span>
                        )}

                        <Button
                            variant="primary"
                            size="sm"
                            onClick={() => setShowConfirm(true)}
                            disabled={starting}
                        >
                            {t('report.startNew')}
                        </Button>
                    </div>
                </div>

                <Card className="flex flex-col gap-6 p-6">
                    <SectionLabel>{t('report.summary')}</SectionLabel>

                    <div className="flex items-start gap-6">
                        {review.overall_score !== null && (
                            <ScoreDisplay
                                score={review.overall_score}
                                qualityLabel={{
                                    good: t('report.quality.good'),
                                    fair: t('report.quality.fair'),
                                    needsWork: t('report.quality.needsWork'),
                                }}
                            />
                        )}
                        <div className="flex min-w-0 flex-1 flex-col gap-2">
                            {review.executive_summary && (
                                <p className="text-[13px] leading-relaxed text-ink-muted">
                                    {review.executive_summary}
                                </p>
                            )}
                        </div>
                    </div>

                    {review.top_strengths &&
                        review.top_improvements &&
                        (review.top_strengths.length > 0 ||
                            review.top_improvements.length > 0) &&
                        !review.is_pre_editorial && (
                            <StrengthsAndImprovements
                                strengths={review.top_strengths}
                                improvements={review.top_improvements}
                            />
                        )}
                </Card>

                {!review.is_pre_editorial && (
                    <>
                        <div className="flex flex-col gap-3">
                            <SectionLabel>
                                {t('report.dimensions')}
                            </SectionLabel>
                            <div className="grid grid-cols-2 gap-4 xl:grid-cols-4">
                                {orderedSections.map((section) => (
                                    <DimensionTile
                                        key={section.id}
                                        label={t(`section.${section.type}`)}
                                        section={section}
                                        openCount={openFindingsCount(section)}
                                        onClick={() =>
                                            openAndScrollTo(section.type)
                                        }
                                    />
                                ))}
                            </div>
                        </div>

                        <div className="flex flex-col gap-4">
                            {orderedSections.map((section) => (
                                <div
                                    key={section.id}
                                    ref={(el) => {
                                        sectionRefs.current[section.type] = el;
                                    }}
                                    className="scroll-mt-6"
                                >
                                    <EditorialReviewSection
                                        section={section}
                                        chapters={chapters}
                                        bookId={review.book_id}
                                        resolvedSet={resolvedSet}
                                        onToggleFinding={handleToggleFinding}
                                        onDiscussFinding={onDiscussFinding}
                                        open={openSections.has(section.type)}
                                        onOpenChange={(open) =>
                                            setSectionOpen(section.type, open)
                                        }
                                    />
                                </div>
                            ))}
                        </div>
                    </>
                )}

                {review.is_pre_editorial && (
                    <Card className="flex flex-col gap-4 p-6">
                        <SectionLabel>{t('preEditorial.heading')}</SectionLabel>
                        <p className="text-[13px] leading-relaxed text-ink-muted">
                            {t('preEditorial.description')}
                        </p>
                        {review.top_improvements &&
                            review.top_improvements.length > 0 && (
                                <BulletList
                                    items={review.top_improvements}
                                    dotColor="bg-accent"
                                />
                            )}
                    </Card>
                )}
            </div>

            {showConfirm && (
                <Dialog onClose={() => setShowConfirm(false)} width={420}>
                    <div className="flex flex-col gap-6">
                        <div className="flex flex-col gap-2">
                            <h2 className="font-serif text-2xl leading-8 font-semibold tracking-[-0.01em] text-ink">
                                {t('confirm.title')}
                            </h2>
                            <p className="text-sm leading-relaxed text-ink-muted">
                                {t('confirm.description')}
                            </p>
                        </div>
                        <div className="flex items-center justify-end gap-3">
                            <Button
                                variant="secondary"
                                onClick={() => setShowConfirm(false)}
                            >
                                {t('common:cancel')}
                            </Button>
                            <Button
                                variant="primary"
                                onClick={() => {
                                    setShowConfirm(false);
                                    onStartNew();
                                }}
                                disabled={starting}
                            >
                                {t('common:confirm')}
                            </Button>
                        </div>
                    </div>
                </Dialog>
            )}
        </>
    );
}
