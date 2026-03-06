import { cn } from '@/lib/utils';
import { CaretDown, Check } from '@phosphor-icons/react';
import { useEffect, useRef, useState } from 'react';

export const FONTS = [
    { id: 'eb-garamond', label: 'EB Garamond', family: "'EB Garamond', ui-serif, Georgia, serif", favorite: true },
    { id: 'palatino', label: 'Palatino', family: "Palatino, 'Palatino Linotype', 'Book Antiqua', serif", favorite: true },
    { id: 'georgia', label: 'Georgia', family: "Georgia, 'Times New Roman', Times, serif", favorite: true },
    { id: 'times', label: 'Times New Roman', family: "'Times New Roman', Times, serif", favorite: false },
    { id: 'courier', label: 'Courier New', family: "'Courier New', Courier, monospace", favorite: false },
] as const;

export const DEFAULT_FONT_ID = 'eb-garamond';

export default function FontSelector({
    value,
    onChange,
}: {
    value: string;
    onChange: (fontId: string) => void;
}) {
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    const selected = FONTS.find((f) => f.id === value) ?? FONTS[0];
    const favorites = FONTS.filter((f) => f.favorite);
    const more = FONTS.filter((f) => !f.favorite);

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
                title="Font"
                className="flex h-7 items-center gap-1 rounded px-2 text-xs text-ink-muted transition-colors hover:bg-neutral-bg hover:text-ink"
            >
                <span style={{ fontFamily: selected.family }} className="text-[13px] leading-none">
                    {selected.label}
                </span>
                <CaretDown size={10} weight="bold" className="shrink-0" />
            </button>

            {open && (
                <div className="absolute left-0 top-full z-50 mt-1 w-[200px] overflow-hidden rounded-lg border border-border bg-surface-card shadow-[0_4px_6px_#1A1A1A0F,0_12px_32px_#1A1A1A1A]">
                    <div className="px-1 pt-2 pb-1">
                        <div className="px-2 py-1 text-[10px] font-medium uppercase leading-3 tracking-[0.08em] text-section-header">
                            Favorites
                        </div>
                        {favorites.map((font) => (
                            <FontItem
                                key={font.id}
                                font={font}
                                isSelected={font.id === value}
                                onSelect={() => {
                                    onChange(font.id);
                                    setOpen(false);
                                }}
                            />
                        ))}
                    </div>
                    <div className="border-t border-border-subtle px-1 py-1 pb-2">
                        <div className="px-2 py-1 text-[10px] font-medium uppercase leading-3 tracking-[0.08em] text-section-header">
                            More
                        </div>
                        {more.map((font) => (
                            <FontItem
                                key={font.id}
                                font={font}
                                isSelected={font.id === value}
                                onSelect={() => {
                                    onChange(font.id);
                                    setOpen(false);
                                }}
                            />
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

function FontItem({
    font,
    isSelected,
    onSelect,
}: {
    font: (typeof FONTS)[number];
    isSelected: boolean;
    onSelect: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onSelect}
            className={cn(
                'flex w-full items-center gap-2 rounded-[5px] px-2 py-1.5 text-left text-[13px] leading-4 hover:bg-neutral-bg',
            )}
        >
            <span className="flex h-3.5 w-3.5 shrink-0 items-center justify-center text-ink-muted">
                {isSelected && <Check size={14} weight="bold" />}
            </span>
            <span className="flex-1 text-ink" style={{ fontFamily: font.family }}>
                {font.label}
            </span>
        </button>
    );
}
