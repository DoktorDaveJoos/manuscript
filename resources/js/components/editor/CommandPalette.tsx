import Kbd from '@/components/ui/Kbd';
import { cn } from '@/lib/utils';
import {
    ArrowRight,
    ArrowsOutLineVertical,
    CornersIn,
    Keyboard,
    Lock,
    MagnifyingGlass,
    Minus,
    NotePencil,
    Plus,
    Sparkle,
    TextB,
    TextItalic,
} from '@phosphor-icons/react';
import type { Editor } from '@tiptap/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

type PaletteItem = {
    id: string;
    label: string;
    shortcut?: string;
    section: string;
    icon: React.ReactNode;
    iconColorClass?: string;
    highlighted?: boolean;
    disabled?: boolean;
    action: () => void;
};

export default function CommandPalette({
    editor,
    isOpen,
    onClose,
    onSplitScene,
    onNewChapter,
    onAddScene,
    onEnterFocusMode,
    isFocusMode,
    onToggleTypewriterMode,
    isTypewriterMode,
    onToggleNotes,
    licensed,
}: {
    editor: Editor | null;
    isOpen: boolean;
    onClose: () => void;
    onSplitScene: () => Promise<void>;
    onNewChapter: () => void;
    onAddScene?: () => void;
    onEnterFocusMode?: () => void;
    isFocusMode?: boolean;
    onToggleTypewriterMode?: () => void;
    isTypewriterMode?: boolean;
    onToggleNotes?: () => void;
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
                label: isFocusMode ? t('palette.leaveFocusMode') : t('palette.enterFocusMode'),
                shortcut: isFocusMode ? 'Esc' : undefined,
                section: t('palette.section.focus'),
                icon: <CornersIn size={14} weight="regular" />,
                action: () => {
                    onClose();
                    onEnterFocusMode?.();
                },
            },
            {
                id: 'typewriter-mode',
                label: isTypewriterMode ? t('palette.leaveTypewriterMode') : t('palette.enterTypewriterMode'),
                section: t('palette.section.focus'),
                icon: <Keyboard size={14} weight="regular" />,
                action: () => {
                    onClose();
                    onToggleTypewriterMode?.();
                },
            },
            {
                id: 'bold',
                label: t('palette.bold'),
                shortcut: '⌘B',
                section: t('palette.section.textStyle'),
                icon: <TextB size={14} weight="bold" />,
                action: run(() => editor!.chain().focus().toggleBold().run()),
            },
            {
                id: 'italic',
                label: t('palette.italic'),
                shortcut: '⌘I',
                section: t('palette.section.textStyle'),
                icon: <TextItalic size={14} weight="regular" />,
                action: run(() => editor!.chain().focus().toggleItalic().run()),
            },
            {
                id: 'ai-generate',
                label: t('palette.generateNextParagraph'),
                shortcut: '⌘↵',
                section: t('palette.section.aiGenerate'),
                iconColorClass: 'text-status-revised',
                highlighted: true,
                disabled: !licensed,
                icon: <Sparkle size={14} weight="fill" />,
                action: () => {},
            },
            {
                id: 'ai-continue',
                label: t('palette.continueFromHere'),
                shortcut: '⌘⇧↵',
                section: t('palette.section.aiGenerate'),
                iconColorClass: 'text-status-revised',
                disabled: !licensed,
                icon: <ArrowRight size={14} weight="regular" />,
                action: () => {},
            },
            {
                id: 'new-chapter',
                label: t('palette.newChapter'),
                section: t('palette.section.chapter'),
                icon: <Plus size={14} weight="regular" />,
                action: run(() => {
                    onNewChapter();
                }),
            },
            {
                id: 'new-scene',
                label: t('palette.newScene'),
                section: t('palette.section.chapter'),
                icon: <Minus size={14} weight="regular" />,
                disabled: !onAddScene,
                action: () => {
                    onAddScene?.();
                    onClose();
                },
            },
            {
                id: 'split-chapter',
                label: t('palette.makeSelectionOwnScene'),
                section: t('palette.section.chapter'),
                icon: <ArrowsOutLineVertical size={14} weight="regular" />,
                action: run(() => {
                    onSplitScene();
                }),
            },
            {
                id: 'toggle-notes',
                label: t('palette.toggleChapterNotes'),
                section: t('palette.section.chapter'),
                icon: <NotePencil size={14} weight="regular" />,
                action: () => {
                    onToggleNotes?.();
                    onClose();
                },
            },
        ];
    }, [editor, onClose, onSplitScene, onNewChapter, onAddScene, onEnterFocusMode, isFocusMode, onToggleTypewriterMode, isTypewriterMode, onToggleNotes, licensed, t]);

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
                setActiveIndex((prev) => (prev - 1 + filtered.length) % filtered.length);
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
        const el = listRef.current.querySelector(`[data-index="${activeIndex}"]`);
        el?.scrollIntoView({ block: 'nearest' });
    }, [activeIndex]);

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-surface/40 pt-[20vh]" onClick={onClose}>
            <div
                className="w-[480px] overflow-hidden rounded-[12px] border border-border bg-surface-card shadow-[0_4px_6px_#1A1A1A0F,0_12px_32px_#1A1A1A1A]"
                onClick={(e) => e.stopPropagation()}
                onKeyDown={handleKeyDown}
            >
                {/* Search input */}
                <div className="flex items-center gap-2 border-b border-border-subtle px-3 py-2.5">
                    <MagnifyingGlass size={14} weight="regular" className="shrink-0 text-ink-faint" />
                    <input
                        ref={inputRef}
                        type="text"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={t('palette.searchActions')}
                        className="min-w-0 flex-1 bg-transparent text-[13px] text-ink placeholder:text-ink-faint focus:outline-none"
                    />
                    <Kbd keys="⇧/" />
                </div>

                {/* Items list */}
                <div ref={listRef}>
                    {filtered.length === 0 && (
                        <div className="px-3 py-4 text-center text-[13px] text-ink-faint">{t('palette.noMatchingActions')}</div>
                    )}
                    {Array.from(sections.entries()).map(([section, sectionItems], index) => {
                        const isFirst = index === 0;
                        const isAiOrChapter = section === 'AI Generate' || section === 'Chapter';

                        return (
                            <div
                                key={section}
                                className={cn(
                                    isFirst ? 'px-1 pt-2 pb-1' : 'border-t border-border-subtle px-1 py-1',
                                    !isFirst && isAiOrChapter && 'pb-2',
                                )}
                            >
                                <div
                                    className={cn(
                                        'flex items-center px-2 pt-1 pb-1.5 text-[10px] font-medium uppercase leading-3 tracking-[0.08em] text-section-header',
                                        section === 'AI Generate' && 'gap-1.5',
                                    )}
                                >
                                    {section === 'AI Generate' && (
                                        <span className="h-[5px] w-[5px] rounded-full bg-status-revised" />
                                    )}
                                    {section}
                                </div>
                                {sectionItems.map((item) => {
                                    const globalIndex = filtered.indexOf(item);
                                    const isActive = globalIndex === activeIndex;
                                    const isDisabled = item.disabled;

                                    return (
                                        <button
                                            key={item.id}
                                            type="button"
                                            data-index={globalIndex}
                                            disabled={isDisabled}
                                            onClick={() => executeItem(globalIndex)}
                                            onMouseEnter={() => setActiveIndex(globalIndex)}
                                            className={cn(
                                                'flex w-full items-center gap-2 rounded-[5px] px-2 py-2 text-left text-[13px] leading-4',
                                                isActive && !isDisabled && 'bg-neutral-bg',
                                                !isActive && item.highlighted && 'bg-surface',
                                                isDisabled && 'pointer-events-none opacity-40',
                                            )}
                                        >
                                            <span className={cn('h-3.5 w-3.5 shrink-0', item.iconColorClass ?? 'text-ink-muted')}>
                                                {item.icon}
                                            </span>
                                            <span className="flex-1 text-ink">{item.label}</span>
                                            {isDisabled && item.id.startsWith('ai-') && (
                                                <span className="flex items-center gap-0.5 rounded bg-ink-faint/10 px-1 py-0.5 text-[10px] font-medium text-ink-faint">
                                                    <Lock size={10} />
                                                    PRO
                                                </span>
                                            )}
                                            {item.shortcut && <Kbd keys={item.shortcut} />}
                                        </button>
                                    );
                                })}
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
