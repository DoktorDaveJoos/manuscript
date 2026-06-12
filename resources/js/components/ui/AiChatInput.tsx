import { ArrowUp, Loader2, Mic, Square } from 'lucide-react';
import {
    forwardRef,
    useEffect,
    useImperativeHandle,
    useRef,
    type KeyboardEvent,
} from 'react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import { useSpeechInput } from '@/hooks/useSpeechInput';
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
 *
 * When local speech input is set up (Whisper model downloaded), a mic button
 * appears beside send: click to record, click again to transcribe into the
 * textarea, Escape discards an active recording.
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
        const { t } = useTranslation('ai');
        const textareaRef = useRef<HTMLTextAreaElement>(null);

        const speech = useSpeechInput((text) => {
            onChange(value.trim() === '' ? text : `${value.replace(/\s+$/, '')} ${text}`);
            textareaRef.current?.focus();
        });

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
            if (e.key === 'Escape' && speech.state === 'recording') {
                e.preventDefault();
                speech.cancel();
                return;
            }
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
                    className={cn(
                        'block min-h-12 w-full resize-none overflow-y-auto rounded-xl bg-transparent py-3.5 pr-14 pl-4 text-sm leading-[1.4] text-ink placeholder:text-ink-faint focus:outline-none disabled:opacity-60',
                        speech.available && 'pr-21',
                    )}
                />
                {speech.available && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={speech.toggle}
                        disabled={disabled || speech.state === 'transcribing'}
                        aria-label={
                            speech.state === 'recording'
                                ? t('speech.stop')
                                : t('speech.start')
                        }
                        className={cn(
                            'absolute right-11 bottom-2 size-8 rounded-full text-ink-muted hover:text-ink',
                            speech.state === 'recording' &&
                                'text-delete hover:text-delete',
                        )}
                    >
                        {speech.state === 'recording' ? (
                            <Square className="size-4 animate-pulse" />
                        ) : speech.state === 'transcribing' ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : (
                            <Mic className="size-4" />
                        )}
                    </Button>
                )}
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
