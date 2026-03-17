import { Heading, List, MessageSquare, Minus, SquareCheckBig } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { BlockType } from '@/components/editor/NotesPanel';

type SlashMenuItem = {
    icon: React.ComponentType<{ size?: number; className?: string }>;
    labelKey: string;
    descriptionKey: string;
    blockType: BlockType;
};

const ITEMS: SlashMenuItem[] = [
    { icon: SquareCheckBig, labelKey: 'notes.slash.todo', descriptionKey: 'notes.slash.todoDescription', blockType: 'todo' },
    { icon: List, labelKey: 'notes.slash.bulletList', descriptionKey: 'notes.slash.bulletListDescription', blockType: 'bullet' },
    { icon: Heading, labelKey: 'notes.slash.heading', descriptionKey: 'notes.slash.headingDescription', blockType: 'heading' },
    { icon: Minus, labelKey: 'notes.slash.divider', descriptionKey: 'notes.slash.dividerDescription', blockType: 'divider' },
    { icon: MessageSquare, labelKey: 'notes.slash.callout', descriptionKey: 'notes.slash.calloutDescription', blockType: 'callout' },
];

export default function NotesSlashMenu({
    position,
    query,
    onSelect,
    onClose,
}: {
    position: { top: number; left: number; flip: boolean };
    query: string;
    onSelect: (blockType: BlockType) => void;
    onClose: () => void;
}) {
    const { t } = useTranslation('editor');
    const [activeIndex, setActiveIndex] = useState(0);
    const activeIndexRef = useRef(0);
    const menuRef = useRef<HTMLDivElement>(null);
    const onSelectRef = useRef(onSelect);
    const onCloseRef = useRef(onClose);
    useEffect(() => {
        onSelectRef.current = onSelect;
        onCloseRef.current = onClose;
    }, [onSelect, onClose]);

    const [prevQuery, setPrevQuery] = useState(query);
    if (prevQuery !== query) {
        setPrevQuery(query);
        setActiveIndex(0);
    }

    useEffect(() => { activeIndexRef.current = activeIndex; }, [activeIndex]);

    const filtered = query ? ITEMS.filter((item) => item.blockType.includes(query.toLowerCase())) : ITEMS;
    const filteredRef = useRef(filtered);
    useEffect(() => { filteredRef.current = filtered; }, [filtered]);

    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            const items = filteredRef.current;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                e.stopImmediatePropagation();
                if (items.length === 0) return;
                setActiveIndex((i) => {
                    const next = (i + 1) % items.length;
                    activeIndexRef.current = next;
                    return next;
                });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                e.stopImmediatePropagation();
                if (items.length === 0) return;
                setActiveIndex((i) => {
                    const next = (i - 1 + items.length) % items.length;
                    activeIndexRef.current = next;
                    return next;
                });
            } else if (e.key === 'Enter' || e.key === 'Tab') {
                e.preventDefault();
                e.stopImmediatePropagation();
                if (items.length > 0) {
                    const idx = Math.min(activeIndexRef.current, items.length - 1);
                    onSelectRef.current(items[idx].blockType);
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                e.stopImmediatePropagation();
                onCloseRef.current();
            }
        };

        document.addEventListener('keydown', handleKeyDown, true);
        return () => document.removeEventListener('keydown', handleKeyDown, true);
    }, []);

    useEffect(() => {
        const handleClick = (e: MouseEvent) => {
            if (menuRef.current && !menuRef.current.contains(e.target as Node)) {
                onCloseRef.current();
            }
        };
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, []);

    return (
        <div
            ref={menuRef}
            className="absolute z-[9999] w-[220px] rounded-lg border border-border bg-white p-1 shadow-lg"
            style={{
                left: position.left,
                ...(position.flip
                    ? { bottom: `calc(100% - ${position.top}px)` }
                    : { top: position.top }),
            }}
        >
            {filtered.length === 0 ? (
                <div className="px-2.5 py-3 text-center text-[12px] text-ink-faint">
                    {t('notes.slash.noResults')}
                </div>
            ) : (
                filtered.map((item, index) => {
                    const Icon = item.icon;
                    const isActive = index === activeIndex;
                    return (
                        <button
                            key={item.blockType}
                            className={`flex w-full items-center gap-2.5 rounded-md px-2.5 py-1.5 text-left ${
                                isActive ? 'bg-accent-light' : 'hover:bg-neutral-bg'
                            }`}
                            onMouseEnter={() => setActiveIndex(index)}
                            onClick={() => onSelect(item.blockType)}
                        >
                            <Icon
                                size={16}
                                className={isActive ? 'shrink-0 text-accent' : 'shrink-0 text-ink-muted'}
                            />
                            <div className="min-w-0">
                                <div className="text-[13px] font-medium leading-tight text-ink">
                                    {t(item.labelKey)}
                                </div>
                                <div className="text-[11px] leading-tight text-ink-faint">
                                    {t(item.descriptionKey)}
                                </div>
                            </div>
                        </button>
                    );
                })
            )}
        </div>
    );
}
