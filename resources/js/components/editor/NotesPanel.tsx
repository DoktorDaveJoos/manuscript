import { updateNotes } from '@/actions/App/Http/Controllers/ChapterController';
import { getXsrfToken } from '@/lib/csrf';
import { jsonFetchHeaders } from '@/lib/utils';
import Kbd from '@/components/ui/Kbd';
import { Notepad, X } from '@phosphor-icons/react';
import { useCallback, useEffect, useRef, useState } from 'react';

type SaveStatus = 'idle' | 'saving' | 'saved';

export default function NotesPanel({
    bookId,
    chapterId,
    initialNotes,
    isFocusMode,
    toggleTick,
    onClose,
}: {
    bookId: number;
    chapterId: number;
    initialNotes: string | null;
    isFocusMode: boolean;
    toggleTick?: number;
    onClose?: () => void;
}) {
    const [isOpen, setIsOpen] = useState(false);
    const [notes, setNotes] = useState(initialNotes ?? '');
    const [saveStatus, setSaveStatus] = useState<SaveStatus>('idle');
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const abortRef = useRef<AbortController | null>(null);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const pendingRef = useRef<string | null>(null);
    const savedTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const prevToggleTickRef = useRef(toggleTick);

    // External toggle signal from CommandPalette
    useEffect(() => {
        if (toggleTick !== prevToggleTickRef.current) {
            prevToggleTickRef.current = toggleTick;
            setIsOpen((prev) => !prev);
        }
    }, [toggleTick]);

    // Auto-focus textarea when the card opens
    useEffect(() => {
        if (isOpen) {
            requestAnimationFrame(() => textareaRef.current?.focus());
        }
    }, [isOpen]);

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

    // ESC closes the card
    useEffect(() => {
        if (!isOpen) return;
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                e.stopPropagation();
                flush();
                setIsOpen(false);
                onClose?.();
            }
        };
        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [isOpen, flush]);

    if (isFocusMode) return null;

    const hasNotes = notes.trim().length > 0;

    if (!isOpen) {
        return (
            <button
                type="button"
                onClick={() => setIsOpen(true)}
                className={`absolute right-6 bottom-6 z-30 flex items-center gap-1.5 rounded-full border border-border py-1.5 pr-3 pl-3 shadow-sm focus:outline-none ${
                    hasNotes ? 'bg-surface-warm text-ink-warm' : 'bg-surface text-ink-muted'
                }`}
                style={{ animation: 'notes-enter 200ms ease-out' }}
            >
                <Notepad size={12} weight="fill" />
                <span className="text-[11px] font-medium leading-3.5">Notes</span>
                {hasNotes && <span className="h-[5px] w-[5px] rounded-full bg-ink-muted" />}
            </button>
        );
    }

    return (
        <div
            className="absolute right-6 bottom-6 z-30 w-[260px] rounded-lg border border-border bg-surface-card"
            style={{
                animation: 'notes-enter 200ms ease-out',
                boxShadow: '0 2px 8px #0000000F, 0 1px 2px #0000000A',
            }}
        >
            <div className="flex items-center justify-between border-b border-border px-3 py-2.5">
                <span className="text-[10px] font-medium uppercase tracking-[0.08em] text-ink-muted">Notes</span>
                <div className="flex items-center gap-2">
                    {saveStatus !== 'idle' && (
                        <span className="text-[10px] text-ink-faint">
                            {saveStatus === 'saving' ? 'Saving\u2026' : 'Saved'}
                        </span>
                    )}
                    <Kbd keys="Esc" />
                    <button
                        type="button"
                        onClick={() => {
                            flush();
                            setIsOpen(false);
                            onClose?.();
                        }}
                        className="flex h-5 w-5 items-center justify-center rounded text-ink-faint transition-colors hover:text-ink"
                    >
                        <X size={12} weight="bold" />
                    </button>
                </div>
            </div>
            <div className="p-3">
                <textarea
                    ref={textareaRef}
                    value={notes}
                    onChange={handleChange}
                    placeholder="Add notes for this chapter..."
                    className="min-h-[148px] w-full resize-y rounded border-0 bg-transparent p-0 font-sans text-[13px] leading-5 text-ink placeholder:text-ink-faint focus:outline-none focus:ring-0"
                />
            </div>
        </div>
    );
}
