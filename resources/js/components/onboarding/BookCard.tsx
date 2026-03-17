import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { editor } from '@/actions/App/Http/Controllers/ChapterController';
import type { Book } from '@/types/models';
import BookCardMenu from './BookCardMenu';
import ProgressBar from './ProgressBar';

type BookWithCounts = Book & {
    chapters_count: number;
    draft_chapters_count: number;
    revised_chapters_count: number;
    final_chapters_count: number;
    chapters_sum_word_count: number | null;
    storylines?: { id: number; book_id: number; name: string }[];
};

function computeHoursAgo(updatedAt: string): number {
    const diff = Date.now() - new Date(updatedAt).getTime();
    return Math.floor(diff / 3600000);
}

export default function BookCard({
    book,
    onRename,
    onDuplicate,
    onDelete,
}: {
    book: BookWithCounts;
    onRename: () => void;
    onDuplicate: () => void;
    onDelete: () => void;
}) {
    const { t, i18n } = useTranslation('onboarding');

    function formatWords(count: number | null): string {
        if (!count) return t('bookCard.words', { count: 0, formatted: '0' });
        return t('bookCard.words', { count, formatted: count.toLocaleString(i18n.language) });
    }

    const [hoursAgo] = useState(() => computeHoursAgo(book.updated_at));
    const updatedAtLabel = (() => {
        if (hoursAgo < 1) return t('bookCard.editedJustNow');
        if (hoursAgo < 24) return t('bookCard.editedHoursAgo', { count: hoursAgo });
        const days = Math.floor(hoursAgo / 24);
        if (days < 30) return t('bookCard.editedDaysAgo', { count: days });
        const months = Math.floor(days / 30);
        return t('bookCard.editedMonthsAgo', { count: months });
    })();

    const storylineCount = book.storylines?.length ?? 0;
    const meta = [
        t('bookCard.chapters', { count: book.chapters_count }),
        formatWords(book.chapters_sum_word_count),
        t('bookCard.storylines', { count: storylineCount }),
    ].join(' \u00B7 ');

    return (
        <div
            onClick={() => router.visit(editor.url(book))}
            className="relative flex w-[400px] shrink-0 cursor-pointer flex-col gap-5 rounded-[10px] border border-border bg-surface-card p-8 transition-shadow hover:shadow-md"
        >
            <div className="absolute right-4 top-4">
                <BookCardMenu onRename={onRename} onDuplicate={onDuplicate} onDelete={onDelete} />
            </div>

            <div className="flex flex-col gap-1.5 pr-8">
                <h3 className="font-serif text-2xl leading-8 tracking-[-0.01em] text-ink">{book.title}</h3>
                <p className="text-[13px] leading-[18px] text-ink-muted">{meta}</p>
            </div>

            <ProgressBar
                counts={{
                    final: book.final_chapters_count,
                    revised: book.revised_chapters_count,
                    draft: book.draft_chapters_count,
                }}
            />

            <span className="text-xs leading-4 text-ink-faint">{updatedAtLabel}</span>
        </div>
    );
}

export type { BookWithCounts };
