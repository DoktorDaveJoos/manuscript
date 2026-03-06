import ReviewChapterRow, { type ReviewChapter } from '@/components/onboarding/ReviewChapterRow';
import type { Book, StorylineType } from '@/types/models';

export type ReviewStoryline = {
    name: string;
    type: StorylineType;
    filename: string;
    chapters: ReviewChapter[];
    notice?: string;
};

const STORYLINE_COLORS: Record<StorylineType, string> = {
    main: '#5B8C5A',
    backstory: '#9B7E5E',
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
    const includedChapters = storylines.flatMap((s) => s.chapters.filter((c) => c.included));
    const totalWords = includedChapters.reduce((sum, c) => sum + c.wordCount, 0);

    function updateChapter(storylineIndex: number, chapterIndex: number, updates: Partial<ReviewChapter>) {
        const next = storylines.map((s, si) => {
            if (si !== storylineIndex) return s;
            return {
                ...s,
                chapters: s.chapters.map((c, ci) => (ci === chapterIndex ? { ...c, ...updates } : c)),
            };
        });
        onUpdate(next);
    }

    return (
        <div className="flex min-h-0 flex-1 flex-col items-center px-10 pt-10 pb-16 gap-8">
            <div className="flex flex-col items-center gap-2">
                <h1 className="font-serif text-[32px] leading-10 tracking-[-0.01em] text-ink">{book.title}</h1>
                <p className="text-sm leading-[18px] text-ink-muted">Review your import</p>
            </div>

            <div className="flex w-[560px] flex-col gap-7">
                {storylines.map((storyline, si) => (
                    <div key={si} className="flex flex-col">
                        <div className="flex items-center gap-2 pb-3">
                            <div
                                className="h-2 w-2 shrink-0 rounded-full"
                                style={{ backgroundColor: STORYLINE_COLORS[storyline.type] }}
                            />
                            <span className="text-sm font-medium leading-[18px] text-ink">{storyline.name}</span>
                            <span className="text-[11px] font-medium uppercase leading-[14px] tracking-[0.05em] text-ink-muted">
                                {storyline.type}
                            </span>
                            <div className="flex-1" />
                            <span className="text-[13px] leading-4 text-ink-faint">{storyline.filename}</span>
                        </div>

                        {storyline.chapters.map((chapter, ci) => (
                            <ReviewChapterRow
                                key={ci}
                                chapter={chapter}
                                onToggle={() => updateChapter(si, ci, { included: !chapter.included })}
                                onTitleChange={(title) => updateChapter(si, ci, { title })}
                            />
                        ))}

                        {storyline.notice && (
                            <span className="pt-1.5 text-xs italic leading-4 text-ink-faint">
                                {storyline.notice}
                            </span>
                        )}
                    </div>
                ))}
            </div>

            <span className="text-[13px] leading-4 text-ink-faint">
                {includedChapters.length} chapter{includedChapters.length === 1 ? '' : 's'} &middot;{' '}
                {totalWords.toLocaleString('en-US')} words
            </span>

            <div className="flex items-center gap-4">
                <button
                    type="button"
                    onClick={onBack}
                    className="rounded-md border border-border px-6 py-2.5 text-sm font-medium leading-[18px] text-ink-muted"
                >
                    Back
                </button>
                <button
                    type="button"
                    onClick={onConfirm}
                    disabled={includedChapters.length === 0 || submitting}
                    className="rounded-md bg-ink px-7 py-2.5 text-sm font-medium leading-[18px] text-surface disabled:opacity-50"
                >
                    {submitting ? 'Importing…' : 'Confirm import'}
                </button>
            </div>
        </div>
    );
}
