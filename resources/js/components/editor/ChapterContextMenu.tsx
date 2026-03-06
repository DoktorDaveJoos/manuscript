import { reorder, updateStatus } from '@/actions/App/Http/Controllers/ChapterController';
import { jsonFetchHeaders } from '@/lib/utils';
import type { Chapter, ChapterStatus, Storyline } from '@/types/models';
import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const statusConfig: { value: ChapterStatus; label: string; dotClass: string }[] = [
    { value: 'draft', label: 'Draft', dotClass: 'bg-status-draft' },
    { value: 'revised', label: 'Revised', dotClass: 'bg-status-revised' },
    { value: 'final', label: 'Final', dotClass: 'bg-status-final' },
];

const menuShadow = 'shadow-[0_4px_24px_#0000001F,0_0_0_1px_#0000000A]';

export default function ChapterContextMenu({
    bookId,
    chapter,
    storylines,
    position,
    onClose,
    onRename,
    onDelete,
}: {
    bookId: number;
    chapter: Chapter;
    storylines: Storyline[];
    position: { x: number; y: number };
    onClose: () => void;
    onRename: () => void;
    onDelete: () => void;
}) {
    const ref = useRef<HTMLDivElement>(null);
    const [statusOpen, setStatusOpen] = useState(false);
    const [moveOpen, setMoveOpen] = useState(false);

    useEffect(() => {
        function handleClickOutside(e: MouseEvent) {
            if (ref.current && !ref.current.contains(e.target as Node)) {
                onClose();
            }
        }

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [onClose]);

    const handleStatusChange = async (status: ChapterStatus) => {
        await fetch(updateStatus.url({ book: bookId, chapter: chapter.id }), {
            method: 'PATCH',
            headers: jsonFetchHeaders(),
            body: JSON.stringify({ status }),
        });
        router.reload({ only: ['book'] });
        onClose();
    };

    const handleMove = (storylineId: number) => {
        const allChapters = storylines.flatMap((s) => (s.chapters ?? []).map((ch) => ({ id: ch.id, storyline_id: ch.storyline_id })));
        const order = allChapters.map((ch) => ({
            id: ch.id,
            storyline_id: ch.id === chapter.id ? storylineId : ch.storyline_id,
        }));

        router.post(
            reorder.url(bookId),
            { order },
            {
                preserveScroll: true,
                onSuccess: () => onClose(),
            },
        );
    };

    const otherStorylines = storylines.filter((s) => s.id !== chapter.storyline_id);
    const itemClass =
        'flex w-full items-center gap-2.5 rounded-[5px] px-3 py-2 text-left text-[13px] leading-[18px] text-ink-soft transition-colors hover:bg-neutral-bg';

    return (
        <div ref={ref} className={`fixed z-50 w-[200px] rounded-lg bg-surface-card ${menuShadow}`} style={{ left: position.x, top: position.y }}>
            <div className="flex flex-col p-1">
                <button
                    type="button"
                    onClick={() => {
                        onClose();
                        onRename();
                    }}
                    className={itemClass}
                >
                    <svg className="shrink-0 text-ink-muted" width="15" height="15" viewBox="0 0 16 16" fill="none">
                        <path d="M11.5 2.5l2 2L5 13H3v-2l8.5-8.5z" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                    </svg>
                    Rename
                </button>

                <div
                    className="relative"
                    onMouseEnter={() => setStatusOpen(true)}
                    onMouseLeave={() => setStatusOpen(false)}
                >
                    <button type="button" className={`${itemClass} justify-between`}>
                        <span className="flex items-center gap-2.5">
                            <svg className="shrink-0 text-ink-muted" width="15" height="15" viewBox="0 0 16 16" fill="none">
                                <circle cx="8" cy="8" r="6" stroke="currentColor" strokeWidth="1.5" />
                                <circle cx="8" cy="8" r="2" fill="currentColor" />
                            </svg>
                            Status
                        </span>
                        <svg className="h-2.5 w-2.5 text-ink-faint" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </button>
                    {statusOpen && (
                        <div className={`absolute left-full top-0 ml-1 w-[160px] rounded-lg bg-surface-card ${menuShadow}`}>
                            <div className="flex flex-col p-1">
                                {statusConfig.map((s) => (
                                    <button
                                        key={s.value}
                                        type="button"
                                        onClick={() => handleStatusChange(s.value)}
                                        className={`${itemClass} ${chapter.status === s.value ? 'font-medium' : ''}`}
                                    >
                                        <span className={`inline-block size-[7px] rounded-full ${s.dotClass}`} />
                                        {s.label}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                {otherStorylines.length > 0 && (
                    <div
                        className="relative"
                        onMouseEnter={() => setMoveOpen(true)}
                        onMouseLeave={() => setMoveOpen(false)}
                    >
                        <button type="button" className={`${itemClass} justify-between`}>
                            <span className="flex items-center gap-2.5">
                                <svg className="shrink-0 text-ink-muted" width="15" height="15" viewBox="0 0 16 16" fill="none">
                                    <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                                Move to
                            </span>
                            <svg className="h-2.5 w-2.5 text-ink-faint" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
                        </button>
                        {moveOpen && (
                            <div className={`absolute left-full top-0 ml-1 w-[180px] rounded-lg bg-surface-card ${menuShadow}`}>
                                <div className="flex flex-col p-1">
                                    {otherStorylines.map((s) => (
                                        <button key={s.id} type="button" onClick={() => handleMove(s.id)} className={itemClass}>
                                            {s.color && <span className="inline-block size-[7px] rounded-full" style={{ backgroundColor: s.color }} />}
                                            {s.name}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                )}

                <div className="mx-2 my-1 h-px bg-border" />

                <button
                    type="button"
                    onClick={() => {
                        onClose();
                        onDelete();
                    }}
                    className="flex w-full items-center gap-2.5 rounded-[5px] px-3 py-2 text-left text-[13px] font-medium leading-[18px] text-delete transition-colors hover:bg-neutral-bg"
                >
                    <svg className="shrink-0" width="15" height="15" viewBox="0 0 16 16" fill="none">
                        <path d="M3 4h10M6 4V3h4v1M5 4l.5 9h5L11 4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                    </svg>
                    Delete
                </button>
            </div>
        </div>
    );
}
