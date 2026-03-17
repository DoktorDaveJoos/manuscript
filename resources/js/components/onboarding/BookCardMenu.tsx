import { Copy, EllipsisVertical, Pencil, Trash2 } from 'lucide-react';
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

        return () =>
            document.removeEventListener('mousedown', handleClickOutside);
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
                className="flex h-7 w-7 items-center justify-center rounded-md text-ink-muted transition-colors hover:bg-neutral-bg hover:text-ink"
            >
                <EllipsisVertical size={14} strokeWidth={2.5} />
            </button>

            {open && (
                <div className="absolute top-full right-0 z-50 mt-1 w-[180px] rounded-lg bg-surface-card shadow-[0_4px_24px_#0000001F,0_0_0_1px_#0000000A]">
                    <div className="flex flex-col p-1">
                        <button
                            type="button"
                            onClick={(e) => {
                                e.stopPropagation();
                                setOpen(false);
                                onRename();
                            }}
                            className="flex w-full items-center gap-2.5 rounded-[5px] px-3 py-2 text-left text-[13px] text-ink-soft transition-colors hover:bg-neutral-bg"
                        >
                            <Pencil
                                size={14}
                                className="shrink-0 text-ink-muted"
                            />
                            {t('bookCardMenu.rename')}
                        </button>

                        <button
                            type="button"
                            onClick={(e) => {
                                e.stopPropagation();
                                setOpen(false);
                                onDuplicate();
                            }}
                            className="flex w-full items-center gap-2.5 rounded-[5px] px-3 py-2 text-left text-[13px] text-ink-soft transition-colors hover:bg-neutral-bg"
                        >
                            <Copy
                                size={14}
                                className="shrink-0 text-ink-muted"
                            />
                            {t('bookCardMenu.duplicate')}
                        </button>

                        <div className="mx-2 my-1 h-px bg-border" />

                        <button
                            type="button"
                            onClick={(e) => {
                                e.stopPropagation();
                                setOpen(false);
                                onDelete();
                            }}
                            className="flex w-full items-center gap-2.5 rounded-[5px] px-3 py-2 text-left text-[13px] font-medium text-delete transition-colors hover:bg-neutral-bg"
                        >
                            <Trash2 size={14} />
                            {t('bookCardMenu.delete')}
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
