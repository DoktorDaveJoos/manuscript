import { ArrowUp } from 'lucide-react';
import {
    forwardRef,
    useEffect,
    useImperativeHandle,
    useRef,
    type KeyboardEvent,
} from 'react';
import Button from '@/components/ui/Button';
import { cn } from '@/lib/utils';

export type AiChatInputHandle = {
    focus: () => void;
    blur: () => void;
};

export type AiChatInputProps = {
    value: string;
    onChange: (value: string) => void;
    onSend: () => void;
    placeholder?: string;
    disabled?: boolean;
    ariaLabel?: string;
    sendAriaLabel?: string;
    /** Auto-grow ceiling in pixels. Defaults to 160. */
    maxHeight?: number;
    className?: string;
};

/**
 * Shared "compose surface" for AI chat inputs: auto-growing textarea wrapped
 * in a soft card with a circular black send button. Enter sends, Shift+Enter
 * inserts a newline. Use everywhere we ask the user to talk to an AI agent.
 */
const AiChatInput = forwardRef<AiChatInputHandle, AiChatInputProps>(
    function AiChatInput(
        {
            value,
            onChange,
            onSend,
            placeholder,
            disabled = false,
            ariaLabel,
            sendAriaLabel,
            maxHeight = 160,
            className,
        },
        ref,
    ) {
        const textareaRef = useRef<HTMLTextAreaElement>(null);

        useImperativeHandle(
            ref,
            () => ({
                focus: () => textareaRef.current?.focus(),
                blur: () => textareaRef.current?.blur(),
            }),
            [],
        );

        useEffect(() => {
            const el = textareaRef.current;
            if (!el) return;
            el.style.height = 'auto';
            el.style.height = `${Math.min(el.scrollHeight, maxHeight)}px`;
        }, [value, maxHeight]);

        const handleKeyDown = (e: KeyboardEvent<HTMLTextAreaElement>) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (!disabled && value.trim() !== '') {
                    onSend();
                }
            }
        };

        const canSend = !disabled && value.trim() !== '';

        return (
            <div
                className={cn(
                    'relative rounded-xl border border-border-light bg-surface-card shadow-sm transition-shadow focus-within:border-border focus-within:shadow-md',
                    className,
                )}
            >
                <textarea
                    ref={textareaRef}
                    rows={1}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    onKeyDown={handleKeyDown}
                    placeholder={placeholder}
                    aria-label={ariaLabel ?? placeholder}
                    disabled={disabled}
                    style={{ maxHeight }}
                    className="block min-h-12 w-full resize-none overflow-y-auto rounded-xl bg-transparent py-3.5 pr-14 pl-4 text-sm leading-[1.4] text-ink placeholder:text-ink-faint focus:outline-none disabled:opacity-60"
                />
                <Button
                    type="button"
                    variant="primary"
                    size="icon"
                    onClick={onSend}
                    disabled={!canSend}
                    aria-label={sendAriaLabel ?? ariaLabel ?? 'Send'}
                    className="absolute right-2 bottom-2 size-8 rounded-full"
                >
                    <ArrowUp className="size-4" />
                </Button>
            </div>
        );
    },
);

export default AiChatInput;
