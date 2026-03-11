import { update } from '@/actions/App/Http/Controllers/StorylineController';
import { jsonFetchHeaders } from '@/lib/utils';
import type { Storyline } from '@/types/models';
import { CaretRight } from '@phosphor-icons/react';
import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import ColorPicker from './ColorPicker';

const menuShadow = 'shadow-[0_4px_24px_#0000001F,0_0_0_1px_#0000000A]';

export default function StorylineContextMenu({
    bookId,
    storyline,
    isLastStoryline,
    position,
    onClose,
    onRename,
    onDelete,
}: {
    bookId: number;
    storyline: Storyline;
    isLastStoryline: boolean;
    position: { x: number; y: number };
    onClose: () => void;
    onRename: () => void;
    onDelete: () => void;
}) {
    const { t } = useTranslation('editor');
    const ref = useRef<HTMLDivElement>(null);
    const [colorOpen, setColorOpen] = useState(false);

    useEffect(() => {
        function handleClickOutside(e: MouseEvent) {
            if (ref.current && !ref.current.contains(e.target as Node)) {
                onClose();
            }
        }

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [onClose]);

    const handleColorChange = async (color: string) => {
        await fetch(update.url({ book: bookId, storyline: storyline.id }), {
            method: 'PATCH',
            headers: jsonFetchHeaders(),
            body: JSON.stringify({ name: storyline.name, color }),
        });
        router.reload({ only: ['book'] });
        onClose();
    };

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
                    {t('contextMenu.rename')}
                </button>

                <div
                    className="relative"
                    onMouseEnter={() => setColorOpen(true)}
                    onMouseLeave={() => setColorOpen(false)}
                >
                    <button type="button" className={`${itemClass} justify-between`}>
                        <span className="flex items-center gap-2.5">
                            {storyline.color && <span className="inline-block size-[7px] rounded-full" style={{ backgroundColor: storyline.color }} />}
                            {t('contextMenu.color')}
                        </span>
                        <CaretRight size={10} weight="bold" className="text-ink-faint" />
                    </button>
                    {colorOpen && (
                        <div className={`absolute left-full top-0 ml-1 w-[170px] rounded-lg bg-surface-card ${menuShadow}`}>
                            <ColorPicker value={storyline.color} onChange={handleColorChange} />
                        </div>
                    )}
                </div>

                <div className="mx-2 my-1 h-px bg-border" />

                <button
                    type="button"
                    disabled={isLastStoryline}
                    onClick={() => {
                        onClose();
                        onDelete();
                    }}
                    className={`flex w-full items-center gap-2.5 rounded-[5px] px-3 py-2 text-left text-[13px] font-medium leading-[18px] transition-colors ${
                        isLastStoryline ? 'cursor-not-allowed text-ink-faint' : 'text-delete hover:bg-neutral-bg'
                    }`}
                >
                    {t('contextMenu.deleteStoryline')}
                </button>
            </div>
        </div>
    );
}
