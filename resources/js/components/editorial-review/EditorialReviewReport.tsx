import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import Dialog from '@/components/ui/Dialog';
import SectionLabel from '@/components/ui/SectionLabel';
import Select from '@/components/ui/Select';
import { useToggleFinding } from '@/hooks/useToggleFinding';
import type {
    Chapter,
    EditorialReview,
    OnDiscussFinding,
} from '@/types/models';
import ChapterProgressStrip from './ChapterProgressStrip';
import EditorialReviewSection from './EditorialReviewSection';

function ScoreDisplay({
    score,
    qualityLabel,
}: {
    score: number;
    qualityLabel: { good: string; fair: string; needsWork: string };
}) {
    return (
        <div className="flex flex-col items-center gap-1 rounded-lg bg-neutral-bg px-5 py-3">
            <span className="font-serif text-[32px] leading-[1] font-semibold text-ink">
                {score}
            </span>
            <span className="text-[11px] font-medium text-ink-faint">
                {score >= 70
                    ? qualityLabel.good
                    : score >= 50
                      ? qualityLabel.fair
                      : qualityLabel.needsWork}
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
            <div className="flex flex-1 flex-col gap-2">
                <SectionLabel>{t('report.strengths')}</SectionLabel>
                <div className="flex flex-col gap-2">
                    {strengths.map((s, i) => (
                        <div key={i} className="flex items-start gap-2">
                            <span className="mt-[6px] size-2 shrink-0 rounded-full bg-status-final" />
                            <span className="text-[13px] leading-relaxed text-ink-muted">
                                {s}
                            </span>
                        </div>
                    ))}
                </div>
            </div>

            <div className="flex flex-1 flex-col gap-2">
                <SectionLabel>{t('report.improvements')}</SectionLabel>
                <div className="flex flex-col gap-2">
                    {improvements.map((imp, i) => (
                        <div key={i} className="flex items-start gap-2">
                            <span className="mt-[6px] size-2 shrink-0 rounded-full bg-accent" />
                            <span className="text-[13px] leading-relaxed text-ink-muted">
                                {imp}
                            </span>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

export default function EditorialReviewReport({
    review,
    reviews,
    chapters,
    onSelectReview,
    onStartNew,
    starting,
    onDiscussFinding,
    onResolvedChange,
}: {
    review: EditorialReview;
    reviews: EditorialReview[];
    chapters: Chapter[];
    onSelectReview: (review: EditorialReview) => void;
    onStartNew: () => void;
    starting: boolean;
    onDiscussFinding: OnDiscussFinding;
    onResolvedChange: (resolved: string[]) => void;
}) {
    const { t, i18n } = useTranslation('editorial-review');
    const [showConfirm, setShowConfirm] = useState(false);

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
        .filter(Boolean);

    return (
        <>
            <div className="flex flex-col gap-6">
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
                        review.top_strengths.length > 0 &&
                        review.top_improvements.length > 0 && (
                            <StrengthsAndImprovements
                                strengths={review.top_strengths}
                                improvements={review.top_improvements}
                            />
                        )}
                </Card>

                <ChapterProgressStrip
                    chapters={chapters}
                    sections={review.sections}
                    resolvedSet={resolvedSet}
                    bookId={review.book_id}
                />

                <div className="flex items-center justify-between">
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

                    <Button
                        variant="primary"
                        size="sm"
                        onClick={() => setShowConfirm(true)}
                        disabled={starting}
                    >
                        {t('report.startNew')}
                    </Button>
                </div>

                <SectionLabel>{t('sectionLabel.editorialReview')}</SectionLabel>

                <div className="flex max-w-3xl flex-col gap-4">
                    {orderedSections.map(
                        (section) =>
                            section && (
                                <EditorialReviewSection
                                    key={section.id}
                                    section={section}
                                    chapters={chapters}
                                    bookId={review.book_id}
                                    resolvedSet={resolvedSet}
                                    onToggleFinding={handleToggleFinding}
                                    onDiscussFinding={onDiscussFinding}
                                />
                            ),
                    )}
                </div>
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
