import { useAiFeatures } from '@/hooks/useAiFeatures';
import { EllipsisVertical, Lock } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

export default function TextActionsDropdown({
    onNormalizeClick,
    onBeautifyClick,
    isBeautifying = false,
}: {
    onNormalizeClick: () => void;
    onBeautifyClick: () => void;
    isBeautifying?: boolean;
}) {
    const { visible, usable, licensed } = useAiFeatures();
    const { t } = useTranslation('editor');
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
                onClick={() => setOpen(!open)}
                title={t('textActions.moreActions')}
                className="flex h-7 w-7 items-center justify-center rounded-md text-xs text-ink-muted transition-colors hover:bg-neutral-bg hover:text-ink"
            >
                <EllipsisVertical size={14} strokeWidth={2.5} />
            </button>

            {open && (
                <div className="absolute right-0 top-full z-50 mt-1 w-[220px] rounded-lg bg-surface-card shadow-[0_4px_24px_#0000001F,0_0_0_1px_#0000000A]">
                    <div className="flex flex-col p-1">
                        <button
                            type="button"
                            onClick={() => {
                                setOpen(false);
                                onNormalizeClick();
                            }}
                            className="w-full rounded-[5px] px-3 py-2 text-left transition-colors hover:bg-neutral-bg"
                        >
                            <span className="block text-xs font-medium text-ink">{t('textActions.normalize')}</span>
                            <span className="block text-[11px] text-ink-faint">
                                {t('textActions.normalizeDescription')}
                            </span>
                        </button>

                        {visible && (
                            <button
                                type="button"
                                disabled={!usable || isBeautifying}
                                onClick={() => {
                                    setOpen(false);
                                    onBeautifyClick();
                                }}
                                className="w-full rounded-[5px] px-3 py-2 text-left transition-colors hover:bg-neutral-bg disabled:opacity-50"
                            >
                                <span className="flex items-center gap-1.5 text-xs font-medium text-ink">
                                    {t('textActions.beautify')}
                                    <span className="rounded bg-status-revised/15 px-1 py-0.5 text-[10px] font-medium text-status-revised">
                                        AI
                                    </span>
                                    {!licensed && (
                                        <span className="flex items-center gap-0.5 rounded bg-ink-faint/10 px-1 py-0.5 text-[10px] font-medium text-ink-faint">
                                            <Lock size={10} />
                                            PRO
                                        </span>
                                    )}
                                </span>
                                <span className="block text-[11px] text-ink-faint">
                                    {isBeautifying ? t('textActions.processing') : t('textActions.beautifyDescription')}
                                </span>
                            </button>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
