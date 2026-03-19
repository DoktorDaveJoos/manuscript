import { ArrowUp, BookOpen, Loader, Sparkles, X } from 'lucide-react';
import MarkdownIt from 'markdown-it';
import { memo, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { chat } from '@/actions/App/Http/Controllers/AiController';
import { useResizablePanel } from '@/hooks/useResizablePanel';
import { jsonFetchHeaders } from '@/lib/utils';
import type {
    Book,
    Chapter,
    Character,
    CharacterChapterPivot,
} from '@/types/models';

const md = new MarkdownIt({ linkify: true, breaks: true });

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

    const {
        width,
        panelRef: asideRef,
        handleMouseDown,
    } = useResizablePanel({
        storageKey: 'ai-chat-drawer-width',
        minWidth: 280,
        maxWidth: 600,
        defaultWidth: 320,
        direction: 'right',
    });

    useEffect(() => {
        inputRef.current?.focus();
    }, []);

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

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
            className="relative flex h-full shrink-0 flex-col border-l border-border-light bg-surface-sidebar"
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
            <div className="flex h-11 items-center justify-between border-b border-border-light px-5">
                <div className="flex items-center gap-2">
                    <Sparkles size={14} className="text-accent" />
                    <span className="text-[11px] font-semibold tracking-[0.06em] text-ink uppercase">
                        {t('askAi')}
                    </span>
                </div>
                <button
                    type="button"
                    onClick={onClose}
                    className="flex size-6 items-center justify-center rounded text-ink-faint transition-colors hover:text-ink"
                >
                    <X size={14} />
                </button>
            </div>

            {/* Chapter Context */}
            <div className="flex flex-col gap-1 border-b border-border-light px-5 py-2.5">
                <div className="flex items-center gap-2">
                    <span className="size-[5px] shrink-0 rounded-full bg-ai-green" />
                    <BookOpen size={13} className="shrink-0 text-accent" />
                    <span className="truncate text-xs font-medium text-ink">
                        {chapter.title || 'Untitled'}
                    </span>
                    <span className="shrink-0 rounded-[3px] bg-neutral-bg px-1.5 py-0.5 text-[10px] font-medium text-ink-muted">
                        {t('chat.chapter', {
                            number: chapter.reader_order + 1,
                        })}
                    </span>
                </div>
                <div className="flex items-center gap-1">
                    <span className="truncate text-[11px] text-ink-faint">
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
                            <div className="max-w-[85%] rounded-2xl bg-neutral-bg px-4 py-3 text-sm leading-relaxed text-ink">
                                {msg.content}
                            </div>
                        </div>
                    ) : (
                        <div key={i} className="flex flex-col gap-1.5">
                            <Sparkles size={14} className="text-accent" />
                            <div className="max-w-[85%] rounded-2xl border border-border-light bg-surface-card px-4 py-3 text-sm leading-relaxed text-ink">
                                {msg.content ? (
                                    <AssistantMessage content={msg.content} />
                                ) : isStreaming && i === messages.length - 1 ? (
                                    <div className="flex items-center gap-[5px]">
                                        <Loader
                                            size={14}
                                            className="animate-spin text-accent"
                                        />
                                        <span className="text-xs tracking-[0.01em] text-ink-muted">
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
            <div className="flex items-center gap-2 border-t border-border-light px-5 py-3">
                <input
                    ref={inputRef}
                    type="text"
                    value={input}
                    onChange={(e) => setInput(e.target.value)}
                    onKeyDown={handleKeyDown}
                    placeholder={t('chat.placeholder')}
                    disabled={isStreaming}
                    className="h-10 flex-1 rounded-lg border border-border bg-surface px-3 text-[13px] text-ink placeholder:text-ink-faint focus:outline-none disabled:opacity-60"
                />
                <button
                    type="button"
                    onClick={handleSend}
                    disabled={!input.trim() || isStreaming}
                    className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-ink text-surface transition-colors hover:bg-ink/90 disabled:opacity-50"
                >
                    <ArrowUp size={14} />
                </button>
            </div>
        </aside>
    );
}
