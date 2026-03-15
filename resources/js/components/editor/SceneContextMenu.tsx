import type { Scene } from '@/types/models';
import { Pencil, Trash2 } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';

const menuShadow = 'shadow-[0_4px_24px_#0000001F,0_0_0_1px_#0000000A]';

export default function SceneContextMenu({
    canDelete,
    position,
    onClose,
    onRename,
    onDelete,
}: {
    scene: Scene;
    canDelete: boolean;
    position: { x: number; y: number };
    onClose: () => void;
    onRename: () => void;
    onDelete: () => void;
}) {
    const { t } = useTranslation('editor');
    const ref = useRef<HTMLDivElement>(null);

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

                {canDelete && (
                    <>
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
                    </>
                )}
            </div>
        </div>
    );
}
