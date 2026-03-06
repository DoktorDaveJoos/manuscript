import { cn } from '@/lib/utils';
import {
    ArrowArcLeft,
    ArrowArcRight,
    Keyboard,
    ListBullets,
    ListNumbers,
    MagnifyingGlass,
    Quotes,
    TextAlignCenter,
    TextAlignLeft,
} from '@phosphor-icons/react';
import type { Editor } from '@tiptap/react';
import { useEditorState } from '@tiptap/react';
import FontSelector from './FontSelector';
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
    isTypewriterMode,
    onTypewriterToggle,
    editorFont,
    onFontChange,
}: {
    editor: Editor | null;
    onNormalizeClick: () => void;
    onBeautifyClick: () => void;
    aiEnabled: boolean;
    isBeautifying?: boolean;
    licensed?: boolean;
    isTypewriterMode?: boolean;
    onTypewriterToggle?: () => void;
    editorFont: string;
    onFontChange: (fontId: string) => void;
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
                <ArrowArcLeft size={14} weight="regular" />
            </ToolbarButton>
            <ToolbarButton
                disabled={!editorState.canRedo}
                onClick={() => run(() => editor!.chain().focus().redo().run())}
                title="Redo"
            >
                <ArrowArcRight size={14} weight="regular" />
            </ToolbarButton>

            <ToolbarDivider />

            <FontSelector value={editorFont} onChange={onFontChange} />

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
                <TextAlignLeft size={14} weight="regular" />
            </ToolbarButton>
            <ToolbarButton
                active={editorState.isAlignCenter}
                onClick={() => run(() => editor!.chain().focus().setTextAlign('center').run())}
                title="Align center"
            >
                <TextAlignCenter size={14} weight="regular" />
            </ToolbarButton>
            <ToolbarButton
                active={editorState.isBlockquote}
                onClick={() => run(() => editor!.chain().focus().toggleBlockquote().run())}
                title="Blockquote"
            >
                <Quotes size={14} weight="regular" />
            </ToolbarButton>
            <ToolbarButton
                active={editorState.isBulletList}
                onClick={() => run(() => editor!.chain().focus().toggleBulletList().run())}
                title="Bullet list"
            >
                <ListBullets size={14} weight="regular" />
            </ToolbarButton>
            <ToolbarButton
                active={editorState.isOrderedList}
                onClick={() => run(() => editor!.chain().focus().toggleOrderedList().run())}
                title="Ordered list"
            >
                <ListNumbers size={14} weight="regular" />
            </ToolbarButton>

            {/* Spacer */}
            <div className="flex-1" />

            {/* Right side */}
            <ToolbarButton
                disabled
                onClick={() => {}}
                title="Search (coming soon)"
            >
                <MagnifyingGlass size={14} weight="regular" />
            </ToolbarButton>
            <ToolbarButton
                active={isTypewriterMode}
                onClick={() => onTypewriterToggle?.()}
                title="Typewriter mode"
            >
                <Keyboard size={14} weight="regular" />
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
