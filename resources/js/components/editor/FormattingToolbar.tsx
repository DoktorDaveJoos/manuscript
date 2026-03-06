import { cn } from '@/lib/utils';
import type { Editor } from '@tiptap/react';
import { useEditorState } from '@tiptap/react';
import TextActionsDropdown from './TextActionsDropdown';

function ToolbarDivider() {
    return <div className="h-4 w-px bg-border" />;
}

function ToolbarButton({
    active,
    disabled,
    onClick,
    title,
    children,
}: {
    active?: boolean;
    disabled?: boolean;
    onClick: () => void;
    title: string;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            title={title}
            className={cn(
                'flex h-7 w-7 items-center justify-center rounded text-xs transition-colors',
                active ? 'bg-neutral-bg text-ink' : 'text-ink-muted hover:bg-neutral-bg hover:text-ink',
                disabled && 'cursor-not-allowed opacity-40',
            )}
        >
            {children}
        </button>
    );
}

export default function FormattingToolbar({
    editor,
    onNormalizeClick,
    onBeautifyClick,
    aiEnabled,
    isBeautifying,
    licensed = true,
}: {
    editor: Editor | null;
    onNormalizeClick: () => void;
    onBeautifyClick: () => void;
    aiEnabled: boolean;
    isBeautifying?: boolean;
    licensed?: boolean;
}) {
    const defaultState = {
        canUndo: false,
        canRedo: false,
        isBold: false,
        isItalic: false,
        isUnderline: false,
        isStrike: false,
        isAlignLeft: true,
        isAlignCenter: false,
        isAlignRight: false,
        isBlockquote: false,
        isBulletList: false,
        isOrderedList: false,
    };

    const editorState = useEditorState({
        editor,
        selector: ({ editor: e }) => {
            if (!e) return defaultState;

            return {
                canUndo: e.can().undo(),
                canRedo: e.can().redo(),
                isBold: e.isActive('bold'),
                isItalic: e.isActive('italic'),
                isUnderline: e.isActive('underline'),
                isStrike: e.isActive('strike'),
                isAlignLeft: e.isActive({ textAlign: 'left' }),
                isAlignCenter: e.isActive({ textAlign: 'center' }),
                isAlignRight: e.isActive({ textAlign: 'right' }),
                isBlockquote: e.isActive('blockquote'),
                isBulletList: e.isActive('bulletList'),
                isOrderedList: e.isActive('orderedList'),
            };
        },
    }) ?? defaultState;

    const run = (cb: () => void) => {
        if (!editor) return;
        cb();
    };

    return (
        <div className="flex h-9 shrink-0 items-center gap-1 border-b border-border px-6">
            {/* History */}
            <ToolbarButton
                disabled={!editorState.canUndo}
                onClick={() => run(() => editor!.chain().focus().undo().run())}
                title="Undo"
            >
                <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                </svg>
            </ToolbarButton>
            <ToolbarButton
                disabled={!editorState.canRedo}
                onClick={() => run(() => editor!.chain().focus().redo().run())}
                title="Redo"
            >
                <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15 15l6-6m0 0l-6-6m6 6H9a6 6 0 000 12h3" />
                </svg>
            </ToolbarButton>

            <ToolbarDivider />

            {/* Text style */}
            <ToolbarButton
                active={editorState.isBold}
                onClick={() => run(() => editor!.chain().focus().toggleBold().run())}
                title="Bold"
            >
                <span className="font-bold">B</span>
            </ToolbarButton>
            <ToolbarButton
                active={editorState.isItalic}
                onClick={() => run(() => editor!.chain().focus().toggleItalic().run())}
                title="Italic"
            >
                <span className="italic">I</span>
            </ToolbarButton>
            <ToolbarButton
                active={editorState.isUnderline}
                onClick={() => run(() => editor!.chain().focus().toggleUnderline().run())}
                title="Underline"
            >
                <span className="underline">U</span>
            </ToolbarButton>
            <ToolbarButton
                active={editorState.isStrike}
                onClick={() => run(() => editor!.chain().focus().toggleStrike().run())}
                title="Strikethrough"
            >
                <span className="line-through">S</span>
            </ToolbarButton>

            <ToolbarDivider />

            {/* Block types */}
            <ToolbarButton
                active={editorState.isAlignLeft}
                onClick={() => run(() => editor!.chain().focus().setTextAlign('left').run())}
                title="Align left"
            >
                <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" d="M3 6h18M3 12h12M3 18h18" />
                </svg>
            </ToolbarButton>
            <ToolbarButton
                active={editorState.isAlignCenter}
                onClick={() => run(() => editor!.chain().focus().setTextAlign('center').run())}
                title="Align center"
            >
                <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" d="M3 6h18M6 12h12M3 18h18" />
                </svg>
            </ToolbarButton>
            <ToolbarButton
                active={editorState.isBlockquote}
                onClick={() => run(() => editor!.chain().focus().toggleBlockquote().run())}
                title="Blockquote"
            >
                <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M8 10h.01M12 10h.01M6 4h12a2 2 0 012 2v8a2 2 0 01-2 2H8l-4 4V6a2 2 0 012-2z" />
                </svg>
            </ToolbarButton>
            <ToolbarButton
                active={editorState.isBulletList}
                onClick={() => run(() => editor!.chain().focus().toggleBulletList().run())}
                title="Bullet list"
            >
                <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" />
                </svg>
            </ToolbarButton>
            <ToolbarButton
                active={editorState.isOrderedList}
                onClick={() => run(() => editor!.chain().focus().toggleOrderedList().run())}
                title="Ordered list"
            >
                <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" d="M10 6h11M10 12h11M10 18h11" />
                    <text x="2" y="8" fontSize="7" fill="currentColor" stroke="none" fontFamily="sans-serif">1</text>
                    <text x="2" y="14" fontSize="7" fill="currentColor" stroke="none" fontFamily="sans-serif">2</text>
                    <text x="2" y="20" fontSize="7" fill="currentColor" stroke="none" fontFamily="sans-serif">3</text>
                </svg>
            </ToolbarButton>

            {/* Spacer */}
            <div className="flex-1" />

            {/* Right side */}
            <ToolbarButton
                disabled
                onClick={() => {}}
                title="Search (coming soon)"
            >
                <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </ToolbarButton>
            <ToolbarButton
                disabled
                onClick={() => {}}
                title="Focus mode (coming soon)"
            >
                <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5v-4m0 4h-4m4 0l-5-5" />
                </svg>
            </ToolbarButton>

            <TextActionsDropdown
                onNormalizeClick={onNormalizeClick}
                onBeautifyClick={onBeautifyClick}
                aiEnabled={aiEnabled}
                isBeautifying={isBeautifying}
                licensed={licensed}
            />
        </div>
    );
}
