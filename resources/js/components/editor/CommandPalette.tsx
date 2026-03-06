import { cn } from '@/lib/utils';
import type { Editor } from '@tiptap/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

type PaletteItem = {
    id: string;
    label: string;
    shortcut?: string;
    section: string;
    icon?: React.ReactNode;
    disabled?: boolean;
    proBadge?: boolean;
    aiTag?: boolean;
    comingSoon?: boolean;
    action: () => void;
};

export default function CommandPalette({
    editor,
    isOpen,
    onClose,
    licensed,
    onSplitChapter,
    onNewChapter,
    onAddScene,
}: {
    editor: Editor | null;
    isOpen: boolean;
    onClose: () => void;
    licensed: boolean;
    onSplitChapter: () => Promise<void>;
    onNewChapter: () => void;
    onAddScene?: () => void;
}) {
    const [query, setQuery] = useState('');
    const [activeIndex, setActiveIndex] = useState(0);
    const inputRef = useRef<HTMLInputElement>(null);
    const listRef = useRef<HTMLDivElement>(null);

    const items: PaletteItem[] = useMemo(() => {
        const run = (cb: () => void) => () => {
            if (!editor) return;
            cb();
            onClose();
        };

        return [
            {
                id: 'bold',
                label: 'Bold',
                shortcut: '⌘B',
                section: 'Text Style',
                icon: (
                    <span className="text-[11px] font-bold">B</span>
                ),
                action: run(() => editor!.chain().focus().toggleBold().run()),
            },
            {
                id: 'italic',
                label: 'Italic',
                shortcut: '⌘I',
                section: 'Text Style',
                icon: (
                    <span className="text-[11px] italic">I</span>
                ),
                action: run(() => editor!.chain().focus().toggleItalic().run()),
            },
            {
                id: 'underline',
                label: 'Underline',
                shortcut: '⌘U',
                section: 'Text Style',
                icon: (
                    <span className="text-[11px] underline">U</span>
                ),
                action: run(() => editor!.chain().focus().toggleUnderline().run()),
            },
            {
                id: 'strikethrough',
                label: 'Strikethrough',
                shortcut: '⌘⇧X',
                section: 'Text Style',
                icon: (
                    <span className="text-[11px] line-through">S</span>
                ),
                action: run(() => editor!.chain().focus().toggleStrike().run()),
            },
            {
                id: 'ai-generate',
                label: 'Generate next paragraph',
                section: 'AI Generate',
                aiTag: true,
                disabled: !licensed,
                proBadge: !licensed,
                comingSoon: licensed,
                action: () => {},
            },
            {
                id: 'ai-continue',
                label: 'Continue from here',
                section: 'AI Generate',
                aiTag: true,
                disabled: !licensed,
                proBadge: !licensed,
                comingSoon: licensed,
                action: () => {},
            },
            {
                id: 'ai-rewrite',
                label: 'Rewrite selection',
                section: 'AI Generate',
                aiTag: true,
                disabled: !licensed,
                proBadge: !licensed,
                comingSoon: licensed,
                action: () => {},
            },
            {
                id: 'ai-dialogue',
                label: 'Suggest dialogue',
                section: 'AI Generate',
                aiTag: true,
                disabled: !licensed,
                proBadge: !licensed,
                comingSoon: licensed,
                action: () => {},
            },
            {
                id: 'split-chapter',
                label: 'New chapter from here',
                section: 'Chapter',
                icon: (
                    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                    </svg>
                ),
                action: run(() => { onSplitChapter(); }),
            },
            {
                id: 'new-chapter',
                label: 'New chapter',
                section: 'Chapter',
                icon: (
                    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                ),
                action: run(() => { onNewChapter(); }),
            },
            {
                id: 'new-scene',
                label: 'Add scene below',
                section: 'Chapter',
                icon: (
                    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M5 12h14" />
                    </svg>
                ),
                disabled: !onAddScene,
                action: () => {
                    onAddScene?.();
                    onClose();
                },
            },
        ];
    }, [editor, licensed, onClose, onSplitChapter, onNewChapter, onAddScene]);

    const filtered = useMemo(() => {
        if (!query.trim()) return items;
        const q = query.toLowerCase();
        return items.filter((item) => item.label.toLowerCase().includes(q));
    }, [items, query]);

    const sections = useMemo(() => {
        const map = new Map<string, PaletteItem[]>();
        for (const item of filtered) {
            const existing = map.get(item.section) ?? [];
            existing.push(item);
            map.set(item.section, existing);
        }
        return map;
    }, [filtered]);

    const flatItems = filtered;

    useEffect(() => {
        setActiveIndex(0);
    }, [query]);

    useEffect(() => {
        if (isOpen) {
            setQuery('');
            setActiveIndex(0);
            requestAnimationFrame(() => inputRef.current?.focus());
        }
    }, [isOpen]);

    const executeItem = useCallback(
        (index: number) => {
            const item = flatItems[index];
            if (item && !item.disabled && !item.comingSoon) {
                item.action();
            }
        },
        [flatItems],
    );

    const handleKeyDown = useCallback(
        (e: React.KeyboardEvent) => {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                setActiveIndex((prev) => (prev + 1) % flatItems.length);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                setActiveIndex((prev) => (prev - 1 + flatItems.length) % flatItems.length);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                executeItem(activeIndex);
            } else if (e.key === 'Escape') {
                e.preventDefault();
                onClose();
            }
        },
        [activeIndex, flatItems.length, executeItem, onClose],
    );

    // Scroll active item into view
    useEffect(() => {
        if (!listRef.current) return;
        const el = listRef.current.querySelector(`[data-index="${activeIndex}"]`);
        el?.scrollIntoView({ block: 'nearest' });
    }, [activeIndex]);

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center pt-[20vh]" onClick={onClose}>
            <div
                className="w-[320px] animate-in fade-in zoom-in-95 rounded-lg border border-border bg-surface-card shadow-lg duration-150"
                onClick={(e) => e.stopPropagation()}
                onKeyDown={handleKeyDown}
            >
                {/* Search input */}
                <div className="flex items-center gap-2 border-b border-border px-3 py-2.5">
                    <svg
                        className="h-3.5 w-3.5 shrink-0 text-ink-faint"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        strokeWidth={2}
                    >
                        <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        ref={inputRef}
                        type="text"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search actions..."
                        className="min-w-0 flex-1 bg-transparent text-xs text-ink placeholder:text-ink-faint focus:outline-none"
                    />
                    <kbd className="shrink-0 rounded border border-border bg-neutral-bg px-1.5 py-0.5 text-[10px] text-ink-muted">
                        ⇧Tab
                    </kbd>
                </div>

                {/* Items list */}
                <div ref={listRef} className="max-h-[320px] overflow-y-auto py-1.5">
                    {flatItems.length === 0 && (
                        <div className="px-3 py-4 text-center text-xs text-ink-faint">No matching actions</div>
                    )}
                    {Array.from(sections.entries()).map(([section, sectionItems]) => (
                        <div key={section}>
                            <div className="px-3 pb-1 pt-2 text-[10px] font-medium uppercase tracking-wider text-ink-faint">
                                {section}
                            </div>
                            {sectionItems.map((item) => {
                                const globalIndex = flatItems.indexOf(item);
                                const isActive = globalIndex === activeIndex;
                                const isDisabled = item.disabled || item.comingSoon;

                                return (
                                    <button
                                        key={item.id}
                                        type="button"
                                        data-index={globalIndex}
                                        disabled={isDisabled}
                                        onClick={() => executeItem(globalIndex)}
                                        onMouseEnter={() => setActiveIndex(globalIndex)}
                                        className={cn(
                                            'flex w-full items-center gap-2.5 px-3 py-1.5 text-left text-xs transition-colors',
                                            isActive && !isDisabled && 'bg-neutral-bg',
                                            isDisabled && 'pointer-events-none opacity-40',
                                        )}
                                    >
                                        {/* Icon */}
                                        <span className="flex h-5 w-5 shrink-0 items-center justify-center text-ink-muted">
                                            {item.aiTag ? (
                                                <span className="h-1.5 w-1.5 rounded-full bg-status-revised" />
                                            ) : (
                                                item.icon
                                            )}
                                        </span>

                                        {/* Label */}
                                        <span className="flex-1 text-ink">{item.label}</span>

                                        {/* Badges */}
                                        {item.comingSoon && (
                                            <span className="rounded bg-ink-faint/10 px-1 py-0.5 text-[10px] text-ink-faint">
                                                Soon
                                            </span>
                                        )}
                                        {item.proBadge && (
                                            <span className="flex items-center gap-0.5 rounded bg-ink-faint/10 px-1 py-0.5 text-[10px] font-medium text-ink-faint">
                                                <svg width="10" height="10" viewBox="0 0 16 16" fill="none">
                                                    <rect x="3" y="7" width="10" height="7" rx="1.5" stroke="currentColor" strokeWidth="1.5" />
                                                    <path d="M5 7V5a3 3 0 016 0v2" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                                                </svg>
                                                PRO
                                            </span>
                                        )}
                                        {item.shortcut && !item.proBadge && (
                                            <kbd className="text-[10px] text-ink-faint">{item.shortcut}</kbd>
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
