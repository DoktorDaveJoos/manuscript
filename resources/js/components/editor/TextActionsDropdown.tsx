import { DotsThreeVertical, Lock } from '@phosphor-icons/react';
import { useEffect, useRef, useState } from 'react';

export default function TextActionsDropdown({
    onNormalizeClick,
    onBeautifyClick,
    aiEnabled,
    isBeautifying = false,
    licensed = true,
}: {
    onNormalizeClick: () => void;
    onBeautifyClick: () => void;
    aiEnabled: boolean;
    isBeautifying?: boolean;
    licensed?: boolean;
}) {
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
                title="More actions"
                className="flex h-7 w-7 items-center justify-center rounded text-xs text-ink-muted transition-colors hover:bg-neutral-bg hover:text-ink"
            >
                <DotsThreeVertical size={14} weight="bold" />
            </button>

            {open && (
                <div className="absolute right-0 top-full z-50 mt-1 w-[220px] rounded-lg border border-border bg-surface-card shadow-lg">
                    <div className="flex flex-col py-1">
                        <button
                            type="button"
                            onClick={() => {
                                setOpen(false);
                                onNormalizeClick();
                            }}
                            className="px-3 py-2 text-left transition-colors hover:bg-neutral-bg"
                        >
                            <span className="block text-xs font-medium text-ink">Normalize</span>
                            <span className="block text-[11px] text-ink-faint">
                                Fix whitespace, quotes, and dashes
                            </span>
                        </button>

                        <button
                            type="button"
                            disabled={!aiEnabled || !licensed || isBeautifying}
                            onClick={() => {
                                setOpen(false);
                                onBeautifyClick();
                            }}
                            className="px-3 py-2 text-left transition-colors hover:bg-neutral-bg disabled:opacity-50"
                        >
                            <span className="flex items-center gap-1.5 text-xs font-medium text-ink">
                                Beautify
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
                                {isBeautifying ? 'Processing...' : 'Restructure paragraphs and dialogue'}
                            </span>
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
