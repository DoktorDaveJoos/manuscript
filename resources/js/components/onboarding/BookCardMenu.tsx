import { Copy, DotsThreeVertical, PencilSimple, Trash } from '@phosphor-icons/react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

export default function BookCardMenu({
    onRename,
    onDuplicate,
    onDelete,
}: {
    onRename: () => void;
    onDuplicate: () => void;
    onDelete: () => void;
}) {
    const { t } = useTranslation('onboarding');
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;

        function handleClickOutside(e: MouseEvent) {
            if (ref.current && !ref.current.contains(e.target as Node)) {
                setOpen(false);
            }
        }

        document.addEventListener('mousedown', handleClickOutside);

        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [open]);

    return (
        <div ref={ref} className="relative">
            <button
                type="button"
                onClick={(e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    setOpen(!open);
                }}
                className="flex h-7 w-7 items-center justify-center rounded text-ink-muted transition-colors hover:bg-neutral-bg hover:text-ink"
            >
                <DotsThreeVertical size={16} weight="bold" />
            </button>

            {open && (
                <div className="absolute right-0 top-full z-50 mt-1 w-[180px] rounded-lg border border-border bg-surface-card shadow-lg">
                    <div className="flex flex-col py-1">
                        <button
                            type="button"
                            onClick={(e) => {
                                e.stopPropagation();
                                setOpen(false);
                                onRename();
                            }}
                            className="flex items-center gap-2.5 px-3 py-2 text-left text-[13px] text-ink transition-colors hover:bg-neutral-bg"
                        >
                            <PencilSimple size={14} className="text-ink-muted" />
                            {t('bookCardMenu.rename')}
                        </button>

                        <button
                            type="button"
                            onClick={(e) => {
                                e.stopPropagation();
                                setOpen(false);
                                onDuplicate();
                            }}
                            className="flex items-center gap-2.5 px-3 py-2 text-left text-[13px] text-ink transition-colors hover:bg-neutral-bg"
                        >
                            <Copy size={14} className="text-ink-muted" />
                            {t('bookCardMenu.duplicate')}
                        </button>

                        <div className="mx-2 my-1 border-t border-border" />

                        <button
                            type="button"
                            onClick={(e) => {
                                e.stopPropagation();
                                setOpen(false);
                                onDelete();
                            }}
                            className="flex items-center gap-2.5 px-3 py-2 text-left text-[13px] text-red-600 transition-colors hover:bg-neutral-bg"
                        >
                            <Trash size={14} />
                            {t('bookCardMenu.delete')}
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
