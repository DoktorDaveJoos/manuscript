import { ChevronRight, ArrowRight, Download, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { Act } from '@/types/models';

const ACT_COLORS: Record<number, string> = {
    0: 'var(--color-accent)',
    1: 'var(--color-status-revised)',
    2: '#A3C4A0',
};

const menuShadow = 'shadow-[0_4px_24px_#0000001F,0_0_0_1px_#0000000A]';

export default function ChapterActContextMenu({
    acts,
    currentActId,
    chapterId,
    position,
    onAssign,
    onExport,
    onClose,
}: {
    acts: Act[];
    currentActId: number | null;
    chapterId?: number;
    position: { x: number; y: number };
    onAssign: (actId: number | null) => void;
    onExport?: (chapterId: number) => void;
    onClose: () => void;
}) {
    const { t } = useTranslation('plot');
    const ref = useRef<HTMLDivElement>(null);
    const [assignOpen, setAssignOpen] = useState(false);

    useEffect(() => {
        function handleClickOutside(e: MouseEvent) {
            if (ref.current && !ref.current.contains(e.target as Node)) {
                onClose();
            }
        }

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [onClose]);

    const itemClass =
        'flex w-full items-center gap-2.5 rounded-[5px] px-3 py-2 text-left text-[13px] leading-[18px] text-ink-soft transition-colors hover:bg-neutral-bg';

    return (
        <div ref={ref} className={`fixed z-50 w-[200px] rounded-lg bg-surface-card ${menuShadow}`} style={{ left: position.x, top: position.y }}>
            <div className="flex flex-col p-1">
                <div
                    className="relative"
                    onMouseEnter={() => setAssignOpen(true)}
                    onMouseLeave={() => setAssignOpen(false)}
                >
                    <button type="button" className={`${itemClass} justify-between`}>
                        <span className="flex items-center gap-2.5">
                            <ArrowRight size={14} className="shrink-0 text-ink-muted" />
                            {t('contextMenu.assignTo')}
                        </span>
                        <ChevronRight size={10} strokeWidth={2.5} className="text-ink-faint" />
                    </button>
                    {assignOpen && (
                        <div className={`absolute left-full top-0 ml-1 w-[180px] rounded-lg bg-surface-card ${menuShadow}`}>
                            <div className="flex flex-col p-1">
                                {acts.map((act, i) => (
                                    <button
                                        key={act.id}
                                        type="button"
                                        onClick={() => {
                                            onAssign(act.id);
                                            onClose();
                                        }}
                                        className={itemClass}
                                    >
                                        <span
                                            className="inline-block size-[7px] rounded-full"
                                            style={{ backgroundColor: ACT_COLORS[i] ?? 'var(--color-accent)' }}
                                        />
                                        {t('actTitle', { number: act.number, title: act.title })}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                {currentActId != null && (
                    <button
                        type="button"
                        onClick={() => {
                            onAssign(null);
                            onClose();
                        }}
                        className={itemClass}
                    >
                        <X size={14} className="shrink-0 text-ink-muted" />
                        {t('contextMenu.unassign')}
                    </button>
                )}

                {onExport && chapterId != null && (
                    <>
                        <div className="my-1 border-t border-border-light" />
                        <button
                            type="button"
                            onClick={() => {
                                onExport(chapterId);
                                onClose();
                            }}
                            className={itemClass}
                        >
                            <Download size={14} className="shrink-0 text-ink-muted" />
                            {t('contextMenu.exportChapter')}
                        </button>
                    </>
                )}
            </div>
        </div>
    );
}
