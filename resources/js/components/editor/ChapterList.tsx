import type { Storyline } from '@/types/models';
import ChapterListItem from './ChapterListItem';

export default function ChapterList({
    storylines,
    bookId,
    activeChapterId,
    onBeforeNavigate,
    onAddChapter,
}: {
    storylines: Storyline[];
    bookId: number;
    activeChapterId?: number;
    onBeforeNavigate?: () => Promise<void>;
    onAddChapter?: (storylineId: number) => void;
}) {
    let chapterIndex = 1;

    return (
        <div className="flex flex-col gap-4">
            {storylines.map((storyline) => (
                <div key={storyline.id} className="flex flex-col gap-0.5">
                    {storylines.length > 1 && (
                        <span className="px-3 pb-1 text-[11px] font-medium uppercase tracking-[0.06em] text-ink-faint">
                            {storyline.name}
                        </span>
                    )}
                    {storyline.chapters?.map((chapter) => {
                        const index = chapterIndex++;
                        return (
                            <ChapterListItem
                                key={chapter.id}
                                chapter={chapter}
                                bookId={bookId}
                                index={index}
                                isActive={chapter.id === activeChapterId}
                                onBeforeNavigate={onBeforeNavigate}
                            />
                        );
                    })}
                    {onAddChapter && (
                        <button
                            type="button"
                            onClick={() => onAddChapter(storyline.id)}
                            className="mt-0.5 w-full rounded-md px-3 py-1.5 text-left text-[13px] text-ink-faint hover:bg-ink/5"
                        >
                            + Add chapter
                        </button>
                    )}
                </div>
            ))}
        </div>
    );
}
