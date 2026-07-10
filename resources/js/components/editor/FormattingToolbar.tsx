import type { Editor } from '@tiptap/react';
import { useEditorState } from '@tiptap/react';
import {
    Keyboard,
    List,
    ListOrdered,
    Maximize2,
    Strikethrough,
    TextQuote,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';

function ToolbarDivider() {
    return <div className="h-4 w-px bg-border" />;
}

function ToolbarButton({
    active,
    disabled,
    onClick,
    title,
    children,
    testId,
}: {
    active?: boolean;
    disabled?: boolean;
    onClick: () => void;
    title: string;
    children: React.ReactNode;
    testId?: string;
}) {
    return (
        <div className="group relative">
            <button
                type="button"
                onClick={onClick}
                disabled={disabled}
                aria-pressed={active ? true : undefined}
                data-testid={testId}
                className={cn(
                    'flex h-7 w-7 items-center justify-center rounded text-xs transition-colors',
                    active
                        ? 'bg-neutral-bg text-ink'
                        : 'text-ink-muted hover:bg-neutral-bg hover:text-ink',
                    disabled && 'cursor-not-allowed opacity-40',
                )}
            >
                {children}
            </button>
            <span className="pointer-events-none absolute top-full left-1/2 z-50 mt-1.5 -translate-x-1/2 rounded bg-ink px-2 py-1 text-[11px] whitespace-nowrap text-surface opacity-0 transition-opacity group-hover:opacity-100">
                {title}
            </span>
        </div>
    );
}

export default function FormattingToolbar({
    editor,
    onToggleFocusMode,
    isTypewriterMode,
    onToggleTypewriterMode,
}: {
    editor: Editor | null;
    onToggleFocusMode: () => void;
    isTypewriterMode?: boolean;
    onToggleTypewriterMode?: () => void;
}) {
    const { t } = useTranslation('editor');
    const defaultState = {
        isBold: false,
        isItalic: false,
        isStrike: false,
        isBulletList: false,
        isOrderedList: false,
        isBlockquote: false,
        isH1: false,
        isH2: false,
    };

    const editorState =
        useEditorState({
            editor,
            selector: ({ editor: e }) => {
                if (!e) return defaultState;

                return {
                    isBold: e.isActive('bold'),
                    isItalic: e.isActive('italic'),
                    isStrike: e.isActive('strike'),
                    isBulletList: e.isActive('bulletList'),
                    isOrderedList: e.isActive('orderedList'),
                    isBlockquote: e.isActive('blockquote'),
                    isH1: e.isActive('heading', { level: 1 }),
                    isH2: e.isActive('heading', { level: 2 }),
                };
            },
        }) ?? defaultState;

    const run = (cb: () => void) => {
        if (!editor) return;
        cb();
    };

    return (
        <div className="flex h-[38px] shrink-0 items-center justify-between border-b border-border-subtle px-8">
            <div className="flex items-center gap-1">
                {/* Text style */}
                <div className="flex items-center gap-[6px]">
                    <ToolbarButton
                        active={editorState.isBold}
                        onClick={() =>
                            run(() =>
                                editor!.chain().focus().toggleBold().run(),
                            )
                        }
                        title={t('toolbar.bold')}
                    >
                        <span className="font-bold">B</span>
                    </ToolbarButton>
                    <ToolbarButton
                        active={editorState.isItalic}
                        onClick={() =>
                            run(() =>
                                editor!.chain().focus().toggleItalic().run(),
                            )
                        }
                        title={t('toolbar.italic')}
                    >
                        <span className="italic">I</span>
                    </ToolbarButton>
                    <ToolbarButton
                        active={editorState.isStrike}
                        onClick={() =>
                            run(() =>
                                editor!.chain().focus().toggleStrike().run(),
                            )
                        }
                        title={t('toolbar.strikethrough')}
                    >
                        <Strikethrough size={14} />
                    </ToolbarButton>
                </div>

                <ToolbarDivider />

                {/* Headings */}
                <div className="flex items-center gap-[6px]">
                    <ToolbarButton
                        active={editorState.isH1}
                        onClick={() =>
                            run(() =>
                                editor!
                                    .chain()
                                    .focus()
                                    .toggleHeading({ level: 1 })
                                    .run(),
                            )
                        }
                        title={t('toolbar.heading1')}
                    >
                        <span className="text-[11px] font-semibold">H1</span>
                    </ToolbarButton>
                    <ToolbarButton
                        active={editorState.isH2}
                        onClick={() =>
                            run(() =>
                                editor!
                                    .chain()
                                    .focus()
                                    .toggleHeading({ level: 2 })
                                    .run(),
                            )
                        }
                        title={t('toolbar.heading2')}
                    >
                        <span className="text-[11px] font-semibold">H2</span>
                    </ToolbarButton>
                </div>
            </div>

            {/* Right side */}
            <div className="flex items-center gap-1">
                <ToolbarButton
                    active={editorState.isBlockquote}
                    onClick={() =>
                        run(() =>
                            editor!.chain().focus().toggleBlockquote().run(),
                        )
                    }
                    title={t('toolbar.blockquote')}
                >
                    <TextQuote size={14} />
                </ToolbarButton>
                <ToolbarButton
                    active={editorState.isOrderedList}
                    onClick={() =>
                        run(() =>
                            editor!.chain().focus().toggleOrderedList().run(),
                        )
                    }
                    title={t('toolbar.orderedList')}
                >
                    <ListOrdered size={14} />
                </ToolbarButton>
                <ToolbarButton
                    active={editorState.isBulletList}
                    onClick={() =>
                        run(() =>
                            editor!.chain().focus().toggleBulletList().run(),
                        )
                    }
                    title={t('toolbar.bulletList')}
                >
                    <List size={14} />
                </ToolbarButton>

                <ToolbarDivider />

                <ToolbarButton
                    active={isTypewriterMode}
                    onClick={() => onToggleTypewriterMode?.()}
                    title={t('toolbar.typewriterMode')}
                    testId="typewriter-toggle"
                >
                    <Keyboard size={15} />
                </ToolbarButton>
                <ToolbarButton
                    onClick={onToggleFocusMode}
                    title={t('toolbar.focus')}
                >
                    <Maximize2 size={14} />
                </ToolbarButton>
            </div>
        </div>
    );
}
