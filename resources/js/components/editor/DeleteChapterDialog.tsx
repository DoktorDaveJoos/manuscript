import { destroy } from '@/actions/App/Http/Controllers/ChapterController';
import type { Chapter } from '@/types/models';
import { router } from '@inertiajs/react';
import { useState } from 'react';

export default function DeleteChapterDialog({
    bookId,
    chapter,
    onClose,
}: {
    bookId: number;
    chapter: Chapter;
    onClose: () => void;
}) {
    const [processing, setProcessing] = useState(false);

    function handleDelete() {
        setProcessing(true);
        router.delete(destroy.url({ book: bookId, chapter: chapter.id }), {
            onSuccess: () => onClose(),
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center">
            <div className="absolute inset-0 bg-ink/[0.08]" onClick={onClose} />
            <div className="relative z-10 flex w-[440px] flex-col gap-7 rounded-xl bg-surface-card p-10 shadow-[0_8px_40px_rgba(0,0,0,0.08)]">
                <div className="flex flex-col gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-[10px] bg-delete-bg">
                        <svg className="h-5 w-5 text-delete" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
                            />
                        </svg>
                    </div>
                    <h2 className="font-serif text-[32px] leading-10 tracking-[-0.01em] text-ink">Delete chapter</h2>
                    <p className="text-sm leading-[22px] text-ink-muted">
                        This will permanently delete <span className="font-medium text-ink">{chapter.title}</span> and its version history. This action cannot
                        be undone.
                    </p>
                </div>

                <div className="flex items-center justify-end gap-3">
                    <button type="button" onClick={onClose} className="rounded-md px-5 py-2.5 text-sm font-medium leading-[18px] text-ink-faint">
                        Cancel
                    </button>
                    <button
                        type="button"
                        disabled={processing}
                        onClick={handleDelete}
                        className="rounded-md bg-delete px-6 py-2.5 text-sm font-medium leading-[18px] text-white transition-opacity disabled:opacity-40"
                    >
                        Delete chapter
                    </button>
                </div>
            </div>
        </div>
    );
}
