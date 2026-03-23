import { Building2, BookOpen, MapPin, Package, Plus, User } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { WikiTab } from './WikiTabBar';

const options: { type: WikiTab; icon: typeof User }[] = [
    { type: 'characters', icon: User },
    { type: 'location', icon: MapPin },
    { type: 'organization', icon: Building2 },
    { type: 'item', icon: Package },
    { type: 'lore', icon: BookOpen },
];

export default function AddEntryDropdown({
    onSelect,
}: {
    onSelect: (type: WikiTab) => void;
}) {
    const { t } = useTranslation('wiki');
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;

        const handleClick = (e: MouseEvent) => {
            if (ref.current && !ref.current.contains(e.target as Node)) {
                setOpen(false);
            }
        };

        const handleEscape = (e: KeyboardEvent) => {
            if (e.key === 'Escape') setOpen(false);
        };

        document.addEventListener('mousedown', handleClick);
        document.addEventListener('keydown', handleEscape);
        return () => {
            document.removeEventListener('mousedown', handleClick);
            document.removeEventListener('keydown', handleEscape);
        };
    }, [open]);

    return (
        <div ref={ref} className="relative">
            <button
                onClick={() => setOpen(!open)}
                className="flex h-[26px] w-[26px] items-center justify-center rounded-md border border-border bg-neutral-bg transition-colors hover:bg-border"
            >
                <Plus size={14} className="text-ink-soft" />
            </button>

            {open && (
                <div className="absolute top-full right-0 z-20 mt-1 w-[184px] rounded-lg border border-border bg-surface-card py-1.5 shadow-[0_4px_12px_rgba(0,0,0,0.1)]">
                    {options.map(({ type, icon: Icon }) => (
                        <button
                            key={type}
                            onClick={() => {
                                onSelect(type);
                                setOpen(false);
                            }}
                            className="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-[14px] text-ink transition-colors hover:bg-neutral-bg"
                        >
                            <Icon
                                size={16}
                                className="shrink-0 text-ink-soft"
                            />
                            {t(
                                `dropdown.${type === 'characters' ? 'character' : type}`,
                            )}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
