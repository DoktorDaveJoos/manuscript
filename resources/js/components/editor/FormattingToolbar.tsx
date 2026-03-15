import { cn } from '@/lib/utils';
import {
    AlignCenter,
    AlignLeft,
    Keyboard,
    List,
    ListOrdered,
    Quote,
    Redo2,
    Search,
    Undo2,
} from 'lucide-react';
import type { Editor } from '@tiptap/react';
import { useEditorState } from '@tiptap/react';
import { useTranslation } from 'react-i18next';
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
    isBeautifying,
    isTypewriterMode,
    onTypewriterToggle,
    editorFont,
    onFontChange,
}: {
    editor: Editor | null;
    onNormalizeClick: () => void;
    onBeautifyClick: () => void;
    isBeautifying?: boolean;
    isTypewriterMode?: boolean;
    onTypewriterToggle?: () => void;
    editorFont: string;
    onFontChange: (fontId: string) => void;
}) {
    const { t } = useTranslation('editor');
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
                title={t('toolbar.undo')}
            >
                <Undo2 size={14} />
            </ToolbarButton>
            <ToolbarButton
                disabled={!editorState.canRedo}
                onClick={() => run(() => editor!.chain().focus().redo().run())}
                title={t('toolbar.redo')}
            >
                <Redo2 size={14} />
            </ToolbarButton>

            <ToolbarDivider />

            <FontSelector value={editorFont} onChange={onFontChange} />

            <ToolbarDivider />

            {/* Text style */}
            <ToolbarButton
                active={editorState.isBold}
                onClick={() => run(() => editor!.chain().focus().toggleBold().run())}
                title={t('toolbar.bold')}
            >
                <span className="font-bold">B</span>
            </ToolbarButton>
            <ToolbarButton
                active={editorState.isItalic}
                onClick={() => run(() => editor!.chain().focus().toggleItalic().run())}
                title={t('toolbar.italic')}
            >
                <span className="italic">I</span>
            </ToolbarButton>
            <ToolbarButton
                active={editorState.isUnderline}
                onClick={() => run(() => editor!.chain().focus().toggleUnderline().run())}
                title={t('toolbar.underline')}
            >
                <span className="underline">U</span>
            </ToolbarButton>
            <ToolbarButton
                active={editorState.isStrike}
                onClick={() => run(() => editor!.chain().focus().toggleStrike().run())}
                title={t('toolbar.strikethrough')}
            >
                <span className="line-through">S</span>
            </ToolbarButton>

            <ToolbarDivider />

            {/* Block types */}
            <ToolbarButton
                active={editorState.isAlignLeft}
                onClick={() => run(() => editor!.chain().focus().setTextAlign('left').run())}
                title={t('toolbar.alignLeft')}
            >
                <AlignLeft size={14} />
            </ToolbarButton>
            <ToolbarButton
                active={editorState.isAlignCenter}
                onClick={() => run(() => editor!.chain().focus().setTextAlign('center').run())}
                title={t('toolbar.alignCenter')}
            >
                <AlignCenter size={14} />
            </ToolbarButton>
            <ToolbarButton
                active={editorState.isBlockquote}
                onClick={() => run(() => editor!.chain().focus().toggleBlockquote().run())}
                title={t('toolbar.blockquote')}
            >
                <Quote size={14} />
            </ToolbarButton>
            <ToolbarButton
                active={editorState.isBulletList}
                onClick={() => run(() => editor!.chain().focus().toggleBulletList().run())}
                title={t('toolbar.bulletList')}
            >
                <List size={14} />
            </ToolbarButton>
            <ToolbarButton
                active={editorState.isOrderedList}
                onClick={() => run(() => editor!.chain().focus().toggleOrderedList().run())}
                title={t('toolbar.orderedList')}
            >
                <ListOrdered size={14} />
            </ToolbarButton>

            {/* Spacer */}
            <div className="flex-1" />

            {/* Right side */}
            <ToolbarButton
                disabled
                onClick={() => {}}
                title={t('toolbar.searchComingSoon')}
            >
                <Search size={14} />
            </ToolbarButton>
            <ToolbarButton
                active={isTypewriterMode}
                onClick={() => onTypewriterToggle?.()}
                title={t('toolbar.typewriterMode')}
            >
                <Keyboard size={14} />
            </ToolbarButton>

            <TextActionsDropdown
                onNormalizeClick={onNormalizeClick}
                onBeautifyClick={onBeautifyClick}
                isBeautifying={isBeautifying}
            />
        </div>
    );
}
