import { chat } from '@/actions/App/Http/Controllers/AiController';
import { jsonFetchHeaders } from '@/lib/utils';
import { ChatCircle, PaperPlaneTilt, X } from '@phosphor-icons/react';
import MarkdownIt from 'markdown-it';
import { useCallback, useEffect, useRef, useState } from 'react';

const md = new MarkdownIt({ linkify: true, breaks: true });

const STORAGE_KEY = 'ai-chat-drawer-width';
const MIN_WIDTH = 280;
const MAX_WIDTH = 600;
const DEFAULT_WIDTH = 320;

type Message = {
    role: 'user' | 'assistant';
    content: string;
};

export default function AiChatDrawer({
    bookId,
    chapterId,
    onClose,
}: {
    bookId: number;
    chapterId: number;
    onClose: () => void;
}) {
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
            const newWidth = Math.min(MAX_WIDTH, Math.max(MIN_WIDTH, startWidth + delta));
            widthRef.current = newWidth;
            if (asideRef.current) asideRef.current.style.width = `${newWidth}px`;
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

    // Use ref for input so handleSend doesn't recreate on every keystroke
    const inputValueRef = useRef(input);
    inputValueRef.current = input;

    // Snapshot messages for history without adding to useCallback deps
    const messagesRef = useRef(messages);
    messagesRef.current = messages;

    const handleSend = useCallback(async () => {
        const trimmed = inputValueRef.current.trim();
        if (!trimmed || isStreaming) return;

        // Capture completed messages as history before adding new ones
        const history = messagesRef.current.filter((m) => m.content.length > 0);

        setInput('');
        setMessages((prev) => [...prev, { role: 'user', content: trimmed }]);
        setIsStreaming(true);

        // Add empty assistant message to stream into
        setMessages((prev) => [...prev, { role: 'assistant', content: '' }]);

        try {
            const response = await fetch(chat.url(bookId), {
                method: 'POST',
                headers: {
                    ...jsonFetchHeaders(),
                    Accept: 'text/event-stream',
                },
                body: JSON.stringify({
                    message: trimmed,
                    chapter_id: chapterId,
                    history: history.length > 0 ? history : undefined,
                }),
            });

            if (!response.ok) {
                const errorText = await response.text().catch(() => 'Chat request failed');
                setMessages((prev) => {
                    const updated = [...prev];
                    updated[updated.length - 1] = { role: 'assistant', content: `Error: ${errorText}` };
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

                // Parse SSE events
                const lines = buffer.split('\n');
                buffer = lines.pop() ?? '';

                const appendChunk = (text: string) => {
                    setMessages((prev) => {
                        const updated = [...prev];
                        const last = updated[updated.length - 1];
                        updated[updated.length - 1] = { ...last, content: last.content + text };
                        return updated;
                    });
                };

                for (const line of lines) {
                    if (line.startsWith('data: ')) {
                        const data = line.slice(6);
                        if (data === '[DONE]') continue;
                        try {
                            const parsed = JSON.parse(data);
                            const text = parsed.text ?? parsed.content ?? parsed.delta ?? '';
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
                if (updated.length > 0 && updated[updated.length - 1].role === 'assistant') {
                    updated[updated.length - 1] = {
                        ...updated[updated.length - 1],
                        content: updated[updated.length - 1].content || 'Connection failed. Please try again.',
                    };
                }
                return updated;
            });
        } finally {
            setIsStreaming(false);
        }
    }, [bookId, chapterId, isStreaming]);

    const handleKeyDown = useCallback(
        (e: React.KeyboardEvent) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                handleSend();
            }
        },
        [handleSend],
    );

    return (
        <aside ref={asideRef} className="relative flex h-full shrink-0 flex-col border-l border-border bg-surface-card" style={{ width }}>
            {/* Resize handle */}
            <div
                onMouseDown={handleMouseDown}
                className="group absolute inset-y-0 -left-1 z-10 w-2 cursor-col-resize"
            >
                <div className="absolute inset-y-0 left-[3px] w-px bg-transparent transition-colors group-hover:bg-ink/20" />
            </div>

            {/* Header */}
            <div className="flex h-12 items-center justify-between border-b border-border-subtle px-5">
                <div className="flex items-center gap-2">
                    <ChatCircle size={14} weight="fill" className="text-ink" />
                    <span className="text-xs font-semibold uppercase tracking-[0.06em] text-ink">Ask AI</span>
                </div>
                <button
                    type="button"
                    onClick={onClose}
                    className="flex size-6 items-center justify-center rounded text-ink-faint transition-colors hover:text-ink"
                >
                    <X size={14} weight="bold" />
                </button>
            </div>

            {/* Messages */}
            <div className="flex flex-1 flex-col gap-4 overflow-y-auto p-5">
                {messages.length === 0 && (
                    <p className="text-center text-xs leading-relaxed text-ink-faint">
                        Ask anything about your book — characters, plot, writing style, or chapter feedback.
                    </p>
                )}
                {messages.map((msg, i) => (
                    <div key={i} className={`flex flex-col ${msg.role === 'user' ? 'items-end' : 'items-start'}`}>
                        <span className="mb-1 text-[11px] text-ink-faint">{msg.role === 'user' ? 'You' : 'AI'}</span>
                        <div
                            className={`max-w-[85%] px-3.5 py-2.5 text-[13px] leading-relaxed text-ink ${
                                msg.role === 'user'
                                    ? 'rounded-t-xl rounded-bl-xl rounded-br-sm bg-border'
                                    : 'rounded-t-xl rounded-bl-sm rounded-br-xl border border-border-subtle bg-surface'
                            }`}
                        >
                            {msg.content ? (
                                msg.role === 'assistant' ? (
                                    <div
                                        className="ai-chat-markdown"
                                        dangerouslySetInnerHTML={{ __html: md.render(msg.content) }}
                                    />
                                ) : (
                                    msg.content
                                )
                            ) : (
                                isStreaming && i === messages.length - 1 ? (
                                    <span className="inline-block h-4 w-1 animate-pulse bg-ink-faint" />
                                ) : null
                            )}
                        </div>
                    </div>
                ))}
                <div ref={messagesEndRef} />
            </div>

            {/* Input */}
            <div className="flex items-center gap-2 border-t border-border-subtle px-4 py-3">
                <input
                    ref={inputRef}
                    type="text"
                    value={input}
                    onChange={(e) => setInput(e.target.value)}
                    onKeyDown={handleKeyDown}
                    placeholder="Ask about this book..."
                    disabled={isStreaming}
                    className="h-10 flex-1 rounded-lg border border-border bg-surface px-3 text-[13px] text-ink placeholder:text-ink-faint disabled:opacity-60"
                />
                <button
                    type="button"
                    onClick={handleSend}
                    disabled={!input.trim() || isStreaming}
                    className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-ink text-white transition-colors hover:bg-ink/90 disabled:opacity-40"
                >
                    <PaperPlaneTilt size={14} weight="fill" />
                </button>
            </div>
        </aside>
    );
}
