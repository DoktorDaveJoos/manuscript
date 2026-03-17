import { ArrowUp, BookOpen, Sparkles, X } from 'lucide-react';
import MarkdownIt from 'markdown-it';
import { memo, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { chat } from '@/actions/App/Http/Controllers/AiController';
import { jsonFetchHeaders } from '@/lib/utils';
import type {
    Book,
    Chapter,
    Character,
    CharacterChapterPivot,
} from '@/types/models';

const md = new MarkdownIt({ linkify: true, breaks: true });

const STORAGE_KEY = 'ai-chat-drawer-width';
const MIN_WIDTH = 280;
const MAX_WIDTH = 600;
const DEFAULT_WIDTH = 320;

type Message = {
    role: 'user' | 'assistant';
    content: string;
};

const AssistantMessage = memo(function AssistantMessage({
    content,
}: {
    content: string;
}) {
    const html = useMemo(() => md.render(content), [content]);
    return (
        <div
            className="ai-chat-markdown"
            dangerouslySetInnerHTML={{ __html: html }}
        />
    );
});

type ChapterWithCharacters = Chapter & {
    characters?: (Character & { pivot: CharacterChapterPivot })[];
};

export default function AiChatDrawer({
    book,
    chapter,
    onClose,
}: {
    book: Book;
    chapter: ChapterWithCharacters;
    onClose: () => void;
}) {
    const { t } = useTranslation('ai');
    const [messages, setMessages] = useState<Message[]>([]);
    const [input, setInput] = useState('');
    const [isStreaming, setIsStreaming] = useState(false);
    const messagesEndRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    const [width, setWidth] = useState(() => {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) {
            const parsed = Number(stored);
            if (parsed >= MIN_WIDTH && parsed <= MAX_WIDTH) return parsed;
        }
        return DEFAULT_WIDTH;
    });
    const widthRef = useRef(width);
    widthRef.current = width;
    const asideRef = useRef<HTMLDivElement>(null);
    const dragCleanupRef = useRef<(() => void) | null>(null);

    useEffect(() => {
        inputRef.current?.focus();
    }, []);

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    useEffect(() => {
        return () => dragCleanupRef.current?.();
    }, []);

    const handleMouseDown = useCallback((e: React.MouseEvent) => {
        e.preventDefault();
        const startX = e.clientX;
        const startWidth = widthRef.current;

        document.body.style.cursor = 'col-resize';
        document.body.style.userSelect = 'none';

        const cleanup = () => {
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            document.removeEventListener('mousemove', handleMouseMove);
            document.removeEventListener('mouseup', handleMouseUp);
            dragCleanupRef.current = null;
        };

        const handleMouseMove = (e: MouseEvent) => {
            const delta = startX - e.clientX;
            const newWidth = Math.min(
                MAX_WIDTH,
                Math.max(MIN_WIDTH, startWidth + delta),
            );
            widthRef.current = newWidth;
            if (asideRef.current)
                asideRef.current.style.width = `${newWidth}px`;
        };

        const handleMouseUp = () => {
            setWidth(widthRef.current);
            localStorage.setItem(STORAGE_KEY, String(widthRef.current));
            cleanup();
        };

        dragCleanupRef.current = cleanup;
        document.addEventListener('mousemove', handleMouseMove);
        document.addEventListener('mouseup', handleMouseUp);
    }, []);

    const inputValueRef = useRef(input);
    inputValueRef.current = input;

    const messagesRef = useRef(messages);
    messagesRef.current = messages;

    const handleSend = useCallback(async () => {
        const trimmed = inputValueRef.current.trim();
        if (!trimmed || isStreaming) return;

        const history = messagesRef.current.filter((m) => m.content.length > 0);

        setInput('');
        setMessages((prev) => [...prev, { role: 'user', content: trimmed }]);
        setIsStreaming(true);

        setMessages((prev) => [...prev, { role: 'assistant', content: '' }]);

        try {
            const response = await fetch(chat.url(book.id), {
                method: 'POST',
                headers: {
                    ...jsonFetchHeaders(),
                    Accept: 'text/event-stream',
                },
                body: JSON.stringify({
                    message: trimmed,
                    chapter_id: chapter.id,
                    history: history.length > 0 ? history : undefined,
                }),
            });

            if (!response.ok) {
                const errorText = await response
                    .text()
                    .catch(() => 'Chat request failed');
                setMessages((prev) => {
                    const updated = [...prev];
                    updated[updated.length - 1] = {
                        role: 'assistant',
                        content: `Error: ${errorText}`,
                    };
                    return updated;
                });
                setIsStreaming(false);
                return;
            }

            const reader = response.body?.getReader();
            if (!reader) {
                setIsStreaming(false);
                return;
            }

            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });

                const lines = buffer.split('\n');
                buffer = lines.pop() ?? '';

                const appendChunk = (text: string) => {
                    setMessages((prev) => {
                        const updated = [...prev];
                        const last = updated[updated.length - 1];
                        updated[updated.length - 1] = {
                            ...last,
                            content: last.content + text,
                        };
                        return updated;
                    });
                };

                for (const line of lines) {
                    if (line.startsWith('data: ')) {
                        const data = line.slice(6);
                        if (data === '[DONE]') continue;
                        try {
                            const parsed = JSON.parse(data);
                            const text =
                                parsed.text ??
                                parsed.content ??
                                parsed.delta ??
                                '';
                            if (text) appendChunk(text);
                        } catch {
                            if (data.trim()) appendChunk(data);
                        }
                    }
                }
            }
        } catch {
            setMessages((prev) => {
                const updated = [...prev];
                if (
                    updated.length > 0 &&
                    updated[updated.length - 1].role === 'assistant'
                ) {
                    updated[updated.length - 1] = {
                        ...updated[updated.length - 1],
                        content:
                            updated[updated.length - 1].content ||
                            t('chat.connectionFailed'),
                    };
                }
                return updated;
            });
        } finally {
            setIsStreaming(false);
        }
    }, [book.id, chapter.id, isStreaming, t]);

    const handleKeyDown = useCallback(
        (e: React.KeyboardEvent) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                handleSend();
            }
        },
        [handleSend],
    );

    const characterNames = (chapter.characters ?? [])
        .map((c) => c.name)
        .slice(0, 3);
    const wordCount = chapter.word_count ?? 0;

    return (
        <aside
            ref={asideRef}
            className="relative flex h-full shrink-0 flex-col border-l border-[#F0EFED] bg-white"
            style={{ width }}
        >
            {/* Resize handle */}
            <div
                onMouseDown={handleMouseDown}
                className="group absolute inset-y-0 -left-1 z-10 w-2 cursor-col-resize"
            >
                <div className="absolute inset-y-0 left-[3px] w-px bg-transparent transition-colors group-hover:bg-ink/20" />
            </div>

            {/* Header */}
            <div className="flex h-11 items-center justify-between border-b border-[#F0EFED] px-5">
                <div className="flex items-center gap-2">
                    <Sparkles size={14} className="text-accent" />
                    <span className="text-[11px] font-semibold tracking-[0.06em] text-ink uppercase">
                        {t('askAi')}
                    </span>
                </div>
                <button
                    type="button"
                    onClick={onClose}
                    className="flex size-6 items-center justify-center rounded text-[#B5B5B5] transition-colors hover:text-ink"
                >
                    <X size={14} />
                </button>
            </div>

            {/* Chapter Context */}
            <div className="flex flex-col gap-1 border-b border-[#F0EFED] px-5 py-2.5">
                <div className="flex items-center gap-2">
                    <span className="size-[5px] shrink-0 rounded-full bg-ai-green" />
                    <BookOpen size={13} className="shrink-0 text-accent" />
                    <span className="truncate text-xs font-medium text-ink">
                        {chapter.title || 'Untitled'}
                    </span>
                    <span className="shrink-0 rounded-[3px] bg-[#F0EFED] px-1.5 py-0.5 text-[10px] font-medium text-ink-muted">
                        {t('chat.chapter', {
                            number: chapter.reader_order + 1,
                        })}
                    </span>
                </div>
                <div className="flex items-center gap-1">
                    <span className="truncate text-[11px] text-[#B5B5B5]">
                        {t('chat.fullContext')}
                        {characterNames.length > 0 &&
                            ` · ${characterNames.join(', ')}`}
                        {wordCount > 0 &&
                            ` · ${t('chat.words', { count: wordCount, formattedCount: wordCount.toLocaleString() })}`}
                    </span>
                </div>
                <div className="flex items-center gap-3">
                    <div className="flex items-center gap-1">
                        <span className="size-1 rounded-full bg-ai-green" />
                        <span className="text-[10px] text-ink-muted">
                            {t('chat.chapterLoaded')}
                        </span>
                    </div>
                    <div className="flex items-center gap-1">
                        <span className="size-1 rounded-full bg-ai-green" />
                        <span className="text-[10px] text-ink-muted">
                            {t('chat.bookContext')}
                        </span>
                    </div>
                </div>
            </div>

            {/* Messages */}
            <div className="flex flex-1 flex-col gap-4 overflow-y-auto p-5">
                {messages.length === 0 && (
                    <p className="text-center text-xs leading-relaxed text-ink-faint">
                        {t('chat.emptyState')}
                    </p>
                )}
                {messages.map((msg, i) =>
                    msg.role === 'user' ? (
                        <div key={i} className="flex flex-col items-end gap-1">
                            <div className="max-w-[85%] rounded-2xl bg-[#F5F4F2] px-4 py-3 text-sm leading-relaxed text-ink">
                                {msg.content}
                            </div>
                        </div>
                    ) : (
                        <div key={i} className="flex flex-col gap-1.5">
                            <Sparkles size={14} className="text-accent" />
                            <div className="max-w-[85%] rounded-2xl border border-[#F0EFED] bg-white px-4 py-3 text-sm leading-relaxed text-ink">
                                {msg.content ? (
                                    <AssistantMessage content={msg.content} />
                                ) : isStreaming && i === messages.length - 1 ? (
                                    <div className="flex items-center gap-1.5">
                                        <span className="size-1.5 animate-pulse rounded-full bg-accent opacity-90" />
                                        <span className="size-1.5 animate-pulse rounded-full bg-accent opacity-55 [animation-delay:150ms]" />
                                        <span className="size-1.5 animate-pulse rounded-full bg-accent opacity-30 [animation-delay:300ms]" />
                                        <span className="ml-1 text-xs text-ink-muted">
                                            {t('chat.thinking')}
                                        </span>
                                    </div>
                                ) : null}
                            </div>
                        </div>
                    ),
                )}
                <div ref={messagesEndRef} />
            </div>

            {/* Input */}
            <div className="flex items-center gap-2 border-t border-[#F0EFED] px-5 py-3">
                <input
                    ref={inputRef}
                    type="text"
                    value={input}
                    onChange={(e) => setInput(e.target.value)}
                    onKeyDown={handleKeyDown}
                    placeholder={t('chat.placeholder')}
                    disabled={isStreaming}
                    className="h-10 flex-1 rounded-lg border border-[#E8E8E8] bg-[#FAFAF8] px-3 text-[13px] text-ink placeholder:text-[#B5B5B5] focus:outline-none disabled:opacity-60"
                />
                <button
                    type="button"
                    onClick={handleSend}
                    disabled={!input.trim() || isStreaming}
                    className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-ink text-white transition-colors hover:bg-ink/90 disabled:opacity-50"
                >
                    <ArrowUp size={14} />
                </button>
            </div>
        </aside>
    );
}
