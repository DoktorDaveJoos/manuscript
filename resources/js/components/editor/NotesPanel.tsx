import { updateNotes } from '@/actions/App/Http/Controllers/ChapterController';
import { getXsrfToken } from '@/lib/csrf';
import { jsonFetchHeaders } from '@/lib/utils';
import { X } from '@phosphor-icons/react';
import { useCallback, useEffect, useRef, useState } from 'react';

type SaveStatus = 'idle' | 'saving' | 'saved';

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
    const [saveStatus, setSaveStatus] = useState<SaveStatus>('idle');
    const abortRef = useRef<AbortController | null>(null);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const pendingRef = useRef<string | null>(null);
    const savedTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

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

        setSaveStatus('saving');

        try {
            await fetch(updateNotes.url({ book: bookId, chapter: chapterId }), {
                method: 'PATCH',
                headers: jsonFetchHeaders(),
                body: JSON.stringify({ notes: value || null }),
                signal: controller.signal,
            });

            setSaveStatus('saved');
            if (savedTimerRef.current) clearTimeout(savedTimerRef.current);
            savedTimerRef.current = setTimeout(() => setSaveStatus('idle'), 2000);
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
            if (savedTimerRef.current) {
                clearTimeout(savedTimerRef.current);
            }
            if (pendingRef.current !== null) {
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
        <div
            className="absolute right-6 bottom-6 z-30 w-[280px] rounded-lg border border-border bg-surface-card shadow-lg"
            style={{ animation: 'notes-enter 200ms ease-out' }}
        >
            <div className="flex items-center justify-between border-b border-border-subtle px-3 py-2">
                <span className="text-[10px] font-medium uppercase tracking-[0.08em] text-ink-muted">Notes</span>
                <div className="flex items-center gap-2">
                    {saveStatus !== 'idle' && (
                        <span className="text-[10px] text-ink-faint">
                            {saveStatus === 'saving' ? 'Saving\u2026' : 'Saved'}
                        </span>
                    )}
                    <button
                        type="button"
                        onClick={() => {
                            flush();
                            onClose();
                        }}
                        className="flex h-5 w-5 items-center justify-center rounded text-ink-faint transition-colors hover:text-ink"
                    >
                        <X size={12} weight="bold" />
                    </button>
                </div>
            </div>
            <div className="p-3">
                <textarea
                    value={notes}
                    onChange={handleChange}
                    placeholder="Add notes for this chapter..."
                    className="min-h-[120px] w-full resize-y rounded border-0 bg-transparent p-0 font-sans text-[13px] leading-relaxed text-ink placeholder:text-ink-faint focus:ring-0"
                />
            </div>
        </div>
    );
}
