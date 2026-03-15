import { reorder, updateStatus } from '@/actions/App/Http/Controllers/ChapterController';
import { jsonFetchHeaders } from '@/lib/utils';
import type { Chapter, ChapterStatus, Storyline } from '@/types/models';
import { ArrowRight, ChevronRight, Circle, Pencil, Trash2 } from 'lucide-react';
import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

const statusDotClass: Record<ChapterStatus, string> = {
    draft: 'bg-status-draft',
    revised: 'bg-status-revised',
    final: 'bg-status-final',
};

const statusValues: ChapterStatus[] = ['draft', 'revised', 'final'];

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
    const { t } = useTranslation('editor');
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
                    <Pencil size={14} className="shrink-0 text-ink-muted" />
                    {t('contextMenu.rename')}
                </button>

                <div
                    className="relative"
                    onMouseEnter={() => setStatusOpen(true)}
                    onMouseLeave={() => setStatusOpen(false)}
                >
                    <button type="button" className={`${itemClass} justify-between`}>
                        <span className="flex items-center gap-2.5">
                            <Circle size={14} fill="currentColor" className="shrink-0 text-ink-muted" />
                            {t('contextMenu.status')}
                        </span>
                        <ChevronRight size={10} strokeWidth={2.5} className="text-ink-faint" />
                    </button>
                    {statusOpen && (
                        <div className={`absolute left-full top-0 ml-1 w-[160px] rounded-lg bg-surface-card ${menuShadow}`}>
                            <div className="flex flex-col p-1">
                                {statusValues.map((value) => (
                                    <button
                                        key={value}
                                        type="button"
                                        onClick={() => handleStatusChange(value)}
                                        className={`${itemClass} ${chapter.status === value ? 'font-medium' : ''}`}
                                    >
                                        <span className={`inline-block size-[7px] rounded-full ${statusDotClass[value]}`} />
                                        {t(`status.${value}`)}
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
                                <ArrowRight size={14} className="shrink-0 text-ink-muted" />
                                {t('contextMenu.moveTo')}
                            </span>
                            <ChevronRight size={10} strokeWidth={2.5} className="text-ink-faint" />
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
                    <Trash2 size={14} className="shrink-0" />
                    {t('contextMenu.delete')}
                </button>
            </div>
        </div>
    );
}
