import type { Editor } from '@tiptap/react';
import {
    ArrowBigUp,
    BookOpen,
    Command as CommandIcon,
    CornerDownLeft,
    GitBranch,
    Keyboard,
    Maximize,
    Minus,
    Plus,
    SpellCheck,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import type {
    AccessBarItemConfig,
    PanelId,
} from '@/components/editor/AccessBar';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
    CommandShortcut,
} from '@/components/ui/Command';

function PaletteIcon({ children }: { children: ReactNode }) {
    return (
        <span className="flex size-4 shrink-0 items-center justify-center text-ink-soft">
            {children}
        </span>
    );
}

function ShortcutKeys({ shortcut }: { shortcut: string }) {
    if (shortcut === 'Esc') {
        return <span className="text-xs font-medium">Esc</span>;
    }

    const elements: ReactNode[] = [];
    for (let i = 0; i < shortcut.length; i++) {
        const ch = shortcut[i];
        if (ch === '\u2318') {
            elements.push(<CommandIcon key={i} size={12} />);
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

    return <>{elements}</>;
}

const panelLabelKeys: Record<PanelId, { open: string; close: string }> = {
    notes: { open: 'palette.openNotes', close: 'palette.closeNotes' },
    ai: { open: 'palette.openAiAssistant', close: 'palette.closeAiAssistant' },
    chat: { open: 'palette.openChat', close: 'palette.closeChat' },
    editorial: {
        open: 'palette.openEditorialReview',
        close: 'palette.closeEditorialReview',
    },
};

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
    panelItems,
    openPanels,
    onTogglePanel,
    isTypewriterMode,
    onToggleTypewriterMode,
    isProofreadingEnabled,
    onToggleProofreading,
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
    panelItems: AccessBarItemConfig[];
    openPanels: Set<PanelId>;
    onTogglePanel: (panel: PanelId) => void;
    isTypewriterMode?: boolean;
    onToggleTypewriterMode?: () => void;
    isProofreadingEnabled?: boolean;
    onToggleProofreading?: () => void;
}) {
    const { t } = useTranslation('editor');
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (isOpen) {
            requestAnimationFrame(() => inputRef.current?.focus());
        }
    }, [isOpen]);

    if (!isOpen) return null;

    const focusModeLabel = isFocusMode
        ? t('palette.leaveFocusMode')
        : t('palette.enterFocusMode');

    const typewriterLabel = isTypewriterMode
        ? t('palette.disableTypewriterMode')
        : t('palette.enableTypewriterMode');

    const proofreadingLabel = isProofreadingEnabled
        ? t('palette.disableProofreading')
        : t('palette.enableProofreading');

    const handleSelect = (action: () => void) => {
        action();
        onClose();
    };

    return (
        <div
            className="fixed inset-0 z-50 flex items-start justify-center bg-surface/40 pt-[20vh]"
            onClick={onClose}
        >
            <div
                className="w-[420px] overflow-hidden rounded-xl bg-surface-card shadow-[0_8px_32px_#1A1A1A18,0_2px_8px_#1A1A1A0A]"
                onClick={(e) => e.stopPropagation()}
            >
                <Command>
                    <CommandInput
                        ref={inputRef}
                        placeholder={t('palette.searchActions')}
                        onKeyDown={(e) => {
                            if (e.key === 'Escape') {
                                e.preventDefault();
                                onClose();
                            }
                        }}
                    >
                        <span className="flex items-center gap-1">
                            <span className="inline-flex items-center justify-center rounded-md border border-border bg-surface-card px-[5px] py-[3px]">
                                <CommandIcon
                                    size={12}
                                    className="text-ink-muted"
                                />
                            </span>
                            <span className="inline-flex items-center justify-center rounded-md border border-border bg-surface-card px-[7px] py-[3px] text-[11px] font-medium text-ink-muted">
                                P
                            </span>
                        </span>
                    </CommandInput>
                    <CommandList>
                        <CommandEmpty>
                            {t('palette.noMatchingActions')}
                        </CommandEmpty>

                        <CommandGroup heading={t('palette.section.editor')}>
                            <CommandItem
                                value={focusModeLabel}
                                onSelect={() =>
                                    handleSelect(() => onEnterFocusMode?.())
                                }
                            >
                                <PaletteIcon>
                                    <Maximize size={16} />
                                </PaletteIcon>
                                <span className="flex-1">{focusModeLabel}</span>
                                {isFocusMode && (
                                    <CommandShortcut>
                                        <ShortcutKeys shortcut="Esc" />
                                    </CommandShortcut>
                                )}
                            </CommandItem>

                            <CommandItem
                                value={typewriterLabel}
                                onSelect={() =>
                                    handleSelect(() =>
                                        onToggleTypewriterMode?.(),
                                    )
                                }
                            >
                                <PaletteIcon>
                                    <Keyboard size={16} />
                                </PaletteIcon>
                                <span className="flex-1">
                                    {typewriterLabel}
                                </span>
                            </CommandItem>

                            <CommandItem
                                value={proofreadingLabel}
                                onSelect={() =>
                                    handleSelect(() => onToggleProofreading?.())
                                }
                            >
                                <PaletteIcon>
                                    <SpellCheck size={16} />
                                </PaletteIcon>
                                <span className="flex-1">
                                    {proofreadingLabel}
                                </span>
                            </CommandItem>
                        </CommandGroup>

                        <CommandSeparator />

                        <CommandGroup heading={t('palette.section.assistance')}>
                            {panelItems.map((item) => {
                                const isActive = openPanels.has(item.id);
                                const keys = panelLabelKeys[item.id];
                                const label = t(
                                    isActive ? keys.close : keys.open,
                                );
                                const Icon = item.icon;
                                return (
                                    <CommandItem
                                        key={item.id}
                                        value={label}
                                        onSelect={() =>
                                            handleSelect(() =>
                                                onTogglePanel(item.id),
                                            )
                                        }
                                    >
                                        <PaletteIcon>
                                            <Icon size={16} />
                                        </PaletteIcon>
                                        <span className="flex-1">{label}</span>
                                    </CommandItem>
                                );
                            })}
                        </CommandGroup>

                        <CommandSeparator />

                        <CommandGroup heading={t('palette.section.textStyle')}>
                            <CommandItem
                                value={t('palette.bold')}
                                onSelect={() =>
                                    handleSelect(() =>
                                        editor
                                            ?.chain()
                                            .focus()
                                            .toggleBold()
                                            .run(),
                                    )
                                }
                            >
                                <PaletteIcon>
                                    <span className="text-sm leading-none font-bold">
                                        B
                                    </span>
                                </PaletteIcon>
                                <span className="flex-1">
                                    {t('palette.bold')}
                                </span>
                                <CommandShortcut>
                                    <ShortcutKeys shortcut={'\u2318B'} />
                                </CommandShortcut>
                            </CommandItem>

                            <CommandItem
                                value={t('palette.italic')}
                                onSelect={() =>
                                    handleSelect(() =>
                                        editor
                                            ?.chain()
                                            .focus()
                                            .toggleItalic()
                                            .run(),
                                    )
                                }
                            >
                                <PaletteIcon>
                                    <span className="text-sm leading-none">
                                        I
                                    </span>
                                </PaletteIcon>
                                <span className="flex-1">
                                    {t('palette.italic')}
                                </span>
                                <CommandShortcut>
                                    <ShortcutKeys shortcut={'\u2318I'} />
                                </CommandShortcut>
                            </CommandItem>
                        </CommandGroup>

                        <CommandSeparator />

                        <CommandGroup heading={t('palette.section.chapter')}>
                            <CommandItem
                                value={t('palette.newChapter')}
                                onSelect={() => handleSelect(onNewChapter)}
                            >
                                <PaletteIcon>
                                    <Plus size={16} />
                                </PaletteIcon>
                                <span className="flex-1">
                                    {t('palette.newChapter')}
                                </span>
                            </CommandItem>

                            <CommandItem
                                value={t('palette.newScene')}
                                disabled={!onAddScene}
                                onSelect={() =>
                                    handleSelect(() => onAddScene?.())
                                }
                            >
                                <PaletteIcon>
                                    <Minus size={16} />
                                </PaletteIcon>
                                <span className="flex-1">
                                    {t('palette.newScene')}
                                </span>
                            </CommandItem>

                            <CommandItem
                                value={t('palette.makeSelectionOwnScene')}
                                onSelect={() =>
                                    handleSelect(() => {
                                        onSplitScene();
                                    })
                                }
                            >
                                <PaletteIcon>
                                    <GitBranch size={16} />
                                </PaletteIcon>
                                <span className="flex-1">
                                    {t('palette.makeSelectionOwnScene')}
                                </span>
                            </CommandItem>

                            <CommandItem
                                value={t('palette.makeSelectionOwnChapter')}
                                onSelect={() =>
                                    handleSelect(() => {
                                        onSplitChapter();
                                    })
                                }
                            >
                                <PaletteIcon>
                                    <BookOpen size={16} />
                                </PaletteIcon>
                                <span className="flex-1">
                                    {t('palette.makeSelectionOwnChapter')}
                                </span>
                            </CommandItem>
                        </CommandGroup>
                    </CommandList>
                </Command>
            </div>
        </div>
    );
}
