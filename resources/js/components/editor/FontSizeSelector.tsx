import { cn } from '@/lib/utils';
import { Check, ChevronDown } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

export const FONT_SIZES = [14, 16, 18, 20, 22, 24] as const;

export const DEFAULT_FONT_SIZE = 18;

export default function FontSizeSelector({
    value,
    onChange,
}: {
    value: number;
    onChange: (size: number) => void;
}) {
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

        function handleEscape(e: KeyboardEvent) {
            if (e.key === 'Escape') {
                setOpen(false);
            }
        }

        document.addEventListener('mousedown', handleClickOutside);
        document.addEventListener('keydown', handleEscape);

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
            document.removeEventListener('keydown', handleEscape);
        };
    }, [open]);

    return (
        <div ref={ref} className="relative">
            <button
                type="button"
                onClick={() => setOpen(!open)}
                title={t('toolbar.fontSize')}
                className="flex h-7 items-center gap-0.5 rounded px-1.5 py-1 text-xs text-ink-muted transition-colors hover:bg-neutral-bg hover:text-ink"
            >
                <span>{value}</span>
                <ChevronDown size={12} />
            </button>

            {open && (
                <div className="absolute left-0 top-full z-50 mt-1 w-[120px] overflow-hidden rounded-lg border border-border bg-surface-card shadow-[0_4px_6px_#1414140F,0_12px_32px_#1414141A]">
                    <div className="px-1 py-1">
                        {FONT_SIZES.map((size) => (
                            <button
                                key={size}
                                type="button"
                                onClick={() => {
                                    onChange(size);
                                    setOpen(false);
                                }}
                                className={cn(
                                    'flex w-full items-center gap-2 rounded-[5px] px-2 py-1.5 text-left text-[13px] leading-4 hover:bg-neutral-bg',
                                )}
                            >
                                <span className="flex h-3.5 w-3.5 shrink-0 items-center justify-center text-ink-muted">
                                    {size === value && <Check size={14} strokeWidth={2.5} />}
                                </span>
                                <span className="flex-1 text-ink">{size}px</span>
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
