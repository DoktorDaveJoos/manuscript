import { useTranslation } from 'react-i18next';
import ReviewChapterRow from '@/components/onboarding/ReviewChapterRow';
import type { ReviewChapter } from '@/components/onboarding/ReviewChapterRow';
import Button from '@/components/ui/Button';
import type { Book, StorylineType } from '@/types/models';

export type ReviewStoryline = {
    name: string;
    type: StorylineType;
    filename: string;
    chapters: ReviewChapter[];
    notice?: string;
};

const STORYLINE_COLORS: Record<StorylineType, string> = {
    main: '#4A7C59',
    backstory: '#B87333',
    parallel: '#7B8EA8',
};

export default function ReviewPhase({
    book,
    storylines,
    onBack,
    onConfirm,
    onUpdate,
    submitting = false,
}: {
    book: Book;
    storylines: ReviewStoryline[];
    onBack: () => void;
    onConfirm: () => void;
    onUpdate: (storylines: ReviewStoryline[]) => void;
    submitting?: boolean;
}) {
    const { t, i18n } = useTranslation('onboarding');
    const includedChapters = storylines.flatMap((s) =>
        s.chapters.filter((c) => c.included),
    );
    const totalWords = includedChapters.reduce(
        (sum, c) => sum + c.wordCount,
        0,
    );

    function updateChapter(
        storylineIndex: number,
        chapterIndex: number,
        updates: Partial<ReviewChapter>,
    ) {
        const next = storylines.map((s, si) => {
            if (si !== storylineIndex) return s;
            return {
                ...s,
                chapters: s.chapters.map((c, ci) =>
                    ci === chapterIndex ? { ...c, ...updates } : c,
                ),
            };
        });
        onUpdate(next);
    }

    return (
        <div className="flex min-h-0 flex-1 flex-col items-center gap-8 px-10 pt-10 pb-16">
            <div className="flex flex-col items-center gap-2">
                <h1 className="font-serif text-[32px] leading-10 tracking-[-0.01em] text-ink">
                    {book.title}
                </h1>
                <p className="text-sm leading-[18px] text-ink-muted">
                    {t('reviewPhase.subtitle')}
                </p>
            </div>

            <div className="flex w-[560px] flex-col gap-7">
                {storylines.map((storyline, si) => (
                    <div key={si} className="flex flex-col">
                        <div className="flex items-center gap-2 pb-3">
                            <div
                                className="h-2 w-2 shrink-0 rounded-full"
                                style={{
                                    backgroundColor:
                                        STORYLINE_COLORS[storyline.type],
                                }}
                            />
                            <span className="text-sm leading-[18px] font-medium text-ink">
                                {storyline.name}
                            </span>
                            <span className="text-[11px] leading-[14px] font-medium tracking-[0.05em] text-ink-muted uppercase">
                                {t(
                                    `reviewPhase.storylineType.${storyline.type}`,
                                )}
                            </span>
                            <div className="flex-1" />
                            <span className="text-[13px] leading-4 text-ink-faint">
                                {storyline.filename}
                            </span>
                        </div>

                        {storyline.chapters.map((chapter, ci) => (
                            <ReviewChapterRow
                                key={ci}
                                chapter={chapter}
                                onToggle={() =>
                                    updateChapter(si, ci, {
                                        included: !chapter.included,
                                    })
                                }
                                onTitleChange={(title) =>
                                    updateChapter(si, ci, { title })
                                }
                            />
                        ))}

                        {storyline.notice && (
                            <span className="pt-1.5 text-xs leading-4 text-ink-faint italic">
                                {storyline.notice}
                            </span>
                        )}
                    </div>
                ))}
            </div>

            <span className="text-[13px] leading-4 text-ink-faint">
                {t('reviewPhase.chapterSummary', {
                    count: includedChapters.length,
                    chapterCount: includedChapters.length,
                    wordCount: totalWords.toLocaleString(i18n.language),
                })}
            </span>

            <div className="flex items-center gap-4">
                <Button
                    variant="secondary"
                    size="lg"
                    type="button"
                    onClick={onBack}
                >
                    {t('reviewPhase.back')}
                </Button>
                <Button
                    variant="default"
                    size="lg"
                    type="button"
                    onClick={onConfirm}
                    disabled={includedChapters.length === 0 || submitting}
                >
                    {submitting
                        ? t('reviewPhase.importing')
                        : t('reviewPhase.confirmImport')}
                </Button>
            </div>
        </div>
    );
}
