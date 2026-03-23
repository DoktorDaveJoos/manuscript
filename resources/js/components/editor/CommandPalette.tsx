import type { Editor } from '@tiptap/react';
import {
    ArrowBigUp,
    BookOpen,
    Command,
    CornerDownLeft,
    GitBranch,
    Keyboard,
    Lock,
    Maximize,
    Menu,
    Minus,
    Plus,
    Radio,
    Search,
    StickyNote,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';

type SectionId = 'focus' | 'textStyle' | 'aiGenerate' | 'chapter';

type PaletteItem = {
    id: string;
    label: string;
    shortcut?: string;
    sectionId: SectionId;
    section: string;
    icon: React.ReactNode;
    disabled?: boolean;
    action: () => void;
};

const SECTION_CLASSES: Record<SectionId, string> = {
    focus: 'px-1.5 pt-2.5 pb-1',
    textStyle: 'border-t border-border px-1.5 pt-3 pb-1',
    aiGenerate: 'border-t border-border px-1.5 pt-3 pb-1.5',
    chapter: 'border-t border-border px-1.5 pt-3 pb-2.5',
};

function ShortcutDisplay({ shortcut }: { shortcut: string }) {
    if (shortcut === 'Esc') {
        return <span className="text-xs font-medium text-ink-faint">Esc</span>;
    }

    const elements: React.ReactNode[] = [];
    for (let i = 0; i < shortcut.length; i++) {
        const ch = shortcut[i];
        if (ch === '\u2318') {
            elements.push(<Command key={i} size={12} />);
        } else if (ch === '\u21E7') {
            elements.push(<ArrowBigUp key={i} size={12} />);
        } else if (ch === '\u21B5') {
            elements.push(<CornerDownLeft key={i} size={12} />);
        } else {
            elements.push(
                <span key={i} className="text-xs leading-none font-medium">
                    {ch}
                </span>,
            );
        }
    }

    return (
        <span className="flex items-center gap-[3px] text-ink-faint">
            {elements}
        </span>
    );
}

export default function CommandPalette({
    editor,
    isOpen,
    onClose,
    onSplitScene,
    onSplitChapter,
    onNewChapter,
    onAddScene,
    onEnterFocusMode,
    isFocusMode,
    onToggleNotes,
    isTypewriterMode,
    onToggleTypewriterMode,
    licensed,
}: {
    editor: Editor | null;
    isOpen: boolean;
    onClose: () => void;
    onSplitScene: () => Promise<void>;
    onSplitChapter: () => Promise<void>;
    onNewChapter: () => void;
    onAddScene?: () => void;
    onEnterFocusMode?: () => void;
    isFocusMode?: boolean;
    onToggleNotes?: () => void;
    isTypewriterMode?: boolean;
    onToggleTypewriterMode?: () => void;
    licensed?: boolean;
}) {
    const { t } = useTranslation('editor');
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
                id: 'focus-mode',
                label: isFocusMode
                    ? t('palette.leaveFocusMode')
                    : t('palette.enterFocusMode'),
                shortcut: isFocusMode ? 'Esc' : undefined,
                sectionId: 'focus',
                section: t('palette.section.focus'),
                icon: <Maximize size={16} />,
                action: () => {
                    onClose();
                    onEnterFocusMode?.();
                },
            },
            {
                id: 'open-notes',
                label: t('palette.openNotes'),
                sectionId: 'focus',
                section: t('palette.section.focus'),
                icon: <StickyNote size={16} />,
                action: () => {
                    onToggleNotes?.();
                    onClose();
                },
            },
            {
                id: 'typewriter-mode',
                label: isTypewriterMode
                    ? t('palette.disableTypewriterMode')
                    : t('palette.enableTypewriterMode'),
                sectionId: 'focus',
                section: t('palette.section.focus'),
                icon: <Keyboard size={16} />,
                action: () => {
                    onToggleTypewriterMode?.();
                    onClose();
                },
            },
            {
                id: 'bold',
                label: t('palette.bold'),
                shortcut: '\u2318B',
                sectionId: 'textStyle',
                section: t('palette.section.textStyle'),
                icon: <span className="text-sm leading-none font-bold">B</span>,
                action: run(() => editor!.chain().focus().toggleBold().run()),
            },
            {
                id: 'italic',
                label: t('palette.italic'),
                shortcut: '\u2318I',
                sectionId: 'textStyle',
                section: t('palette.section.textStyle'),
                icon: <span className="text-sm leading-none">I</span>,
                action: run(() => editor!.chain().focus().toggleItalic().run()),
            },
            {
                id: 'ai-generate',
                label: t('palette.generateNextParagraph'),
                shortcut: '\u2318\u21B5',
                sectionId: 'aiGenerate',
                section: t('palette.section.aiGenerate'),
                disabled: !licensed,
                icon: <Menu size={16} />,
                action: () => {},
            },
            {
                id: 'ai-continue',
                label: t('palette.continueFromHere'),
                shortcut: '\u2318\u21E7\u21B5',
                sectionId: 'aiGenerate',
                section: t('palette.section.aiGenerate'),
                disabled: !licensed,
                icon: <Radio size={16} />,
                action: () => {},
            },
            {
                id: 'new-chapter',
                label: t('palette.newChapter'),
                sectionId: 'chapter',
                section: t('palette.section.chapter'),
                icon: <Plus size={16} />,
                action: run(() => {
                    onNewChapter();
                }),
            },
            {
                id: 'new-scene',
                label: t('palette.newScene'),
                sectionId: 'chapter',
                section: t('palette.section.chapter'),
                icon: <Minus size={16} />,
                disabled: !onAddScene,
                action: () => {
                    onAddScene?.();
                    onClose();
                },
            },
            {
                id: 'split-scene',
                label: t('palette.makeSelectionOwnScene'),
                sectionId: 'chapter',
                section: t('palette.section.chapter'),
                icon: <GitBranch size={16} />,
                action: run(() => {
                    onSplitScene();
                }),
            },
            {
                id: 'split-chapter',
                label: t('palette.makeSelectionOwnChapter'),
                sectionId: 'chapter',
                section: t('palette.section.chapter'),
                icon: <BookOpen size={16} />,
                action: run(() => {
                    onSplitChapter();
                }),
            },
        ];
    }, [
        editor,
        onClose,
        onSplitScene,
        onSplitChapter,
        onNewChapter,
        onAddScene,
        onEnterFocusMode,
        isFocusMode,
        onToggleNotes,
        isTypewriterMode,
        onToggleTypewriterMode,
        licensed,
        t,
    ]);

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

    useEffect(() => {
        if (isOpen) {
            requestAnimationFrame(() => {
                setQuery('');
                setActiveIndex(0);
                inputRef.current?.focus();
            });
        }
    }, [isOpen]);

    const executeItem = useCallback(
        (index: number) => {
            const item = filtered[index];
            if (item && !item.disabled) {
                item.action();
            }
        },
        [filtered],
    );

    const handleKeyDown = useCallback(
        (e: React.KeyboardEvent) => {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (filtered.length === 0) return;
                setActiveIndex((prev) => (prev + 1) % filtered.length);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (filtered.length === 0) return;
                setActiveIndex(
                    (prev) => (prev - 1 + filtered.length) % filtered.length,
                );
            } else if (e.key === 'Enter') {
                e.preventDefault();
                executeItem(activeIndex);
            } else if (e.key === 'Escape') {
                e.preventDefault();
                onClose();
            }
        },
        [activeIndex, filtered.length, executeItem, onClose],
    );

    // Scroll active item into view
    useEffect(() => {
        if (!listRef.current) return;
        const el = listRef.current.querySelector(
            `[data-index="${activeIndex}"]`,
        );
        el?.scrollIntoView({ block: 'nearest' });
    }, [activeIndex]);

    if (!isOpen) return null;

    return (
        <div
            className="fixed inset-0 z-50 flex items-start justify-center bg-surface/40 pt-[20vh]"
            onClick={onClose}
        >
            <div
                className="w-[420px] overflow-hidden rounded-xl bg-surface-card shadow-[0_8px_32px_#1A1A1A18,0_2px_8px_#1A1A1A0A]"
                onClick={(e) => e.stopPropagation()}
                onKeyDown={handleKeyDown}
            >
                {/* Search input */}
                <div className="flex items-center gap-2.5 border-b border-border px-4 py-3.5">
                    <Search size={16} className="shrink-0 text-ink-faint" />
                    <input
                        ref={inputRef}
                        type="text"
                        value={query}
                        onChange={(e) => {
                            setQuery(e.target.value);
                            setActiveIndex(0);
                        }}
                        placeholder={t('palette.searchActions')}
                        className="min-w-0 flex-1 bg-transparent text-sm text-ink placeholder:text-ink-faint focus:outline-none"
                    />
                    <span className="flex items-center gap-1">
                        <span className="inline-flex items-center justify-center rounded-md border border-border bg-surface-card px-[5px] py-[3px]">
                            <Command size={12} className="text-ink-muted" />
                        </span>
                        <span className="inline-flex items-center justify-center rounded-md border border-border bg-surface-card px-[7px] py-[3px] text-[11px] font-medium text-ink-muted">
                            P
                        </span>
                    </span>
                </div>

                {/* Items list */}
                <div ref={listRef}>
                    {filtered.length === 0 && (
                        <div className="px-4 py-4 text-center text-sm text-ink-faint">
                            {t('palette.noMatchingActions')}
                        </div>
                    )}
                    {Array.from(sections.entries()).map(
                        ([section, sectionItems]) => {
                            const sid = sectionItems[0]?.sectionId;

                            return (
                                <div
                                    key={section}
                                    className={cn(
                                        'flex flex-col gap-1',
                                        sid && SECTION_CLASSES[sid],
                                    )}
                                >
                                    <div
                                        className={cn(
                                            'flex items-center px-2.5 text-[11px] font-medium tracking-[0.06em] text-ink-faint uppercase',
                                            sid === 'aiGenerate' &&
                                                'gap-1.5 text-accent',
                                        )}
                                    >
                                        {sid === 'aiGenerate' && (
                                            <span className="size-1.5 rounded-full bg-accent" />
                                        )}
                                        {section}
                                    </div>
                                    {sectionItems.map((item) => {
                                        const globalIndex =
                                            filtered.indexOf(item);
                                        const isActive =
                                            globalIndex === activeIndex;
                                        const isDisabled = item.disabled;

                                        return (
                                            <button
                                                key={item.id}
                                                type="button"
                                                data-index={globalIndex}
                                                disabled={isDisabled}
                                                onClick={() =>
                                                    executeItem(globalIndex)
                                                }
                                                onMouseEnter={() =>
                                                    setActiveIndex(globalIndex)
                                                }
                                                className={cn(
                                                    'flex w-full items-center gap-2.5 rounded-[6px] px-2.5 py-2 text-left text-sm leading-4',
                                                    isActive &&
                                                        !isDisabled &&
                                                        'bg-neutral-bg',
                                                    isDisabled &&
                                                        'pointer-events-none opacity-40',
                                                )}
                                            >
                                                <span className="flex size-4 shrink-0 items-center justify-center text-ink-soft">
                                                    {item.icon}
                                                </span>
                                                <span className="flex-1 text-ink">
                                                    {item.label}
                                                </span>
                                                {isDisabled &&
                                                    item.id.startsWith(
                                                        'ai-',
                                                    ) && (
                                                        <span className="flex items-center gap-0.5 rounded bg-ink-faint/10 px-1 py-0.5 text-[11px] font-medium text-ink-faint">
                                                            <Lock size={12} />
                                                            PRO
                                                        </span>
                                                    )}
                                                {item.shortcut && (
                                                    <ShortcutDisplay
                                                        shortcut={item.shortcut}
                                                    />
                                                )}
                                            </button>
                                        );
                                    })}
                                </div>
                            );
                        },
                    )}
                </div>
            </div>
        </div>
    );
}
