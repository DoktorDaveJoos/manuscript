import { updateNotes } from '@/actions/App/Http/Controllers/ChapterController';
import { getXsrfToken } from '@/lib/csrf';
import { useCallback, useEffect, useRef, useState } from 'react';

export default function NotesPanel({
    bookId,
    chapterId,
    initialNotes,
    onClose,
}: {
    bookId: number;
    chapterId: number;
    initialNotes: string | null;
    onClose: () => void;
}) {
    const [notes, setNotes] = useState(initialNotes ?? '');
    const abortRef = useRef<AbortController | null>(null);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const pendingRef = useRef<string | null>(null);

    const flush = useCallback(async () => {
        if (timerRef.current) {
            clearTimeout(timerRef.current);
            timerRef.current = null;
        }

        const value = pendingRef.current;
        if (value === null) return;
        pendingRef.current = null;

        abortRef.current?.abort();
        const controller = new AbortController();
        abortRef.current = controller;

        try {
            await fetch(updateNotes.url({ book: bookId, chapter: chapterId }), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
                body: JSON.stringify({ notes: value || null }),
                signal: controller.signal,
            });
        } catch {
            // Ignore abort errors
        }
    }, [bookId, chapterId]);

    const handleChange = useCallback(
        (e: React.ChangeEvent<HTMLTextAreaElement>) => {
            const value = e.target.value;
            setNotes(value);
            pendingRef.current = value;

            if (timerRef.current) {
                clearTimeout(timerRef.current);
            }

            timerRef.current = setTimeout(() => {
                flush();
            }, 1500);
        },
        [flush],
    );

    // Flush on unmount
    useEffect(() => {
        return () => {
            if (timerRef.current) {
                clearTimeout(timerRef.current);
            }
            if (pendingRef.current !== null) {
                // Fire-and-forget save on unmount
                const value = pendingRef.current;
                pendingRef.current = null;
                fetch(updateNotes.url({ book: bookId, chapter: chapterId }), {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': getXsrfToken(),
                    },
                    body: JSON.stringify({ notes: value || null }),
                });
            }
        };
    }, [bookId, chapterId]);

    return (
        <div className="border-b border-border bg-surface-card px-6 py-3">
            <div className="mb-2 flex items-center justify-between">
                <span className="text-xs font-medium uppercase tracking-[0.06em] text-ink-muted">Notes</span>
                <button
                    type="button"
                    onClick={() => {
                        flush();
                        onClose();
                    }}
                    className="flex h-5 w-5 items-center justify-center rounded text-ink-faint transition-colors hover:text-ink"
                >
                    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <textarea
                value={notes}
                onChange={handleChange}
                placeholder="Add notes for this chapter..."
                className="w-full resize-none rounded border-0 bg-transparent p-0 font-sans text-sm leading-relaxed text-ink placeholder:text-ink-faint focus:ring-0"
                rows={4}
            />
        </div>
    );
}
