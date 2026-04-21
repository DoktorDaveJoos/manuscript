import { ArrowUp, Loader, Sparkles } from 'lucide-react';
import MarkdownIt from 'markdown-it';
import { memo, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    sessionIndex,
    sessionShow,
    stream,
} from '@/actions/App/Http/Controllers/PlotCoachController';
import { extractErrorMessage, jsonFetchHeaders } from '@/lib/utils';

const md = new MarkdownIt({ linkify: true, breaks: true });

type ChatMessage = {
    role: 'user' | 'assistant';
    content: string;
};

type ChatSurfaceProps = {
    bookId: number;
    sessionId: number | null;
    onSessionCreated?: (sessionId: number) => void;
};

type CoachSessionSummary = {
    id: number;
    status: string;
    stage: string;
    archived_at: string | null;
    created_at: string;
    updated_at: string;
};

type CoachSessionShowResponse = {
    id: number;
    messages: ChatMessage[];
};

const AssistantMessage = memo(function AssistantMessage({
    content,
    streaming,
}: {
    content: string;
    streaming?: boolean;
}) {
    const html = useMemo(
        () => (streaming ? null : md.render(content)),
        [content, streaming],
    );
    return html ? (
        <div
            className="ai-chat-markdown text-[14px] leading-[1.55] text-ink"
            dangerouslySetInnerHTML={{ __html: html }}
        />
    ) : (
        <div className="ai-chat-markdown text-[14px] leading-[1.55] whitespace-pre-wrap text-ink">
            {content}
        </div>
    );
});

/**
 * Plot Coach chat surface. Renders hydrated history + streaming live
 * responses, and kicks off the first stream (which creates the session
 * server-side) when `sessionId` is null.
 */
export default function ChatSurface({
    bookId,
    sessionId,
    onSessionCreated,
}: ChatSurfaceProps) {
    const { t } = useTranslation('plot-coach');
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [input, setInput] = useState('');
    const [isStreaming, setIsStreaming] = useState(false);
    const [isLoadingHistory, setIsLoadingHistory] = useState(false);
    const [streamError, setStreamError] = useState<string | null>(null);

    const isStreamingRef = useRef(false);
    const inputValueRef = useRef(input);
    inputValueRef.current = input;

    const messagesEndRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const abortRef = useRef<AbortController | null>(null);
    const lastSentMessageRef = useRef<string | null>(null);

    // Hydrate history for an existing session. Abort on unmount / id change.
    useEffect(() => {
        if (sessionId === null || sessionId === undefined) {
            setMessages([]);
            return;
        }

        const controller = new AbortController();
        setIsLoadingHistory(true);

        const url = sessionShow.url({ book: bookId, session: sessionId });

        fetch(url, {
            headers: jsonFetchHeaders(),
            signal: controller.signal,
        })
            .then((res) => {
                if (!res.ok) throw new Error('Not found');
                return res.json();
            })
            .then((data: CoachSessionShowResponse) => {
                setMessages(data.messages ?? []);
            })
            .catch((err) => {
                if (err?.name === 'AbortError') return;
                setMessages([]);
            })
            .finally(() => setIsLoadingHistory(false));

        return () => controller.abort();
    }, [bookId, sessionId]);

    useEffect(() => {
        inputRef.current?.focus();
    }, []);

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    // Abort any in-flight stream on unmount.
    useEffect(() => {
        return () => {
            abortRef.current?.abort();
        };
    }, []);

    const sendMessage = useCallback(
        async (rawMessage: string) => {
            const trimmed = rawMessage.trim();
            if (!trimmed || isStreamingRef.current) return;

            setStreamError(null);
            setInput('');
            lastSentMessageRef.current = trimmed;

            const hadSessionBeforeSend = sessionId !== null;

            setMessages((prev) => [
                ...prev,
                { role: 'user', content: trimmed },
                { role: 'assistant', content: '' },
            ]);
            isStreamingRef.current = true;
            setIsStreaming(true);

            const controller = new AbortController();
            abortRef.current = controller;

            try {
                const chatUrl = stream.url({ book: bookId });

                const body: Record<string, unknown> = {
                    message: trimmed,
                };
                if (sessionId !== null) {
                    body.session_id = sessionId;
                }

                const response = await fetch(chatUrl, {
                    method: 'POST',
                    headers: {
                        ...jsonFetchHeaders(),
                        Accept: 'text/event-stream',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(body),
                    signal: controller.signal,
                });

                if (!response.ok) {
                    const errorText = await response.text().catch(() => '');
                    const errorMessage = extractErrorMessage(
                        errorText,
                        t('status.error.body'),
                    );
                    setStreamError(errorMessage);
                    setMessages((prev) => {
                        // Drop the trailing empty assistant placeholder.
                        if (
                            prev.length > 0 &&
                            prev[prev.length - 1].role === 'assistant' &&
                            prev[prev.length - 1].content === ''
                        ) {
                            return prev.slice(0, -1);
                        }
                        return prev;
                    });
                    isStreamingRef.current = false;
                    setIsStreaming(false);
                    return;
                }

                const reader = response.body?.getReader();
                if (!reader) {
                    isStreamingRef.current = false;
                    setIsStreaming(false);
                    return;
                }

                const decoder = new TextDecoder();
                let buffer = '';

                const appendChunk = (text: string) => {
                    setMessages((prev) => {
                        const updated = [...prev];
                        const last = updated[updated.length - 1];
                        if (!last || last.role !== 'assistant') return prev;
                        updated[updated.length - 1] = {
                            ...last,
                            content: last.content + text,
                        };
                        return updated;
                    });
                };

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value, { stream: true });

                    const lines = buffer.split('\n');
                    buffer = lines.pop() ?? '';

                    let pendingText = '';

                    for (const line of lines) {
                        if (!line.startsWith('data: ')) continue;

                        const data = line.slice(6);
                        if (data === '[DONE]') continue;

                        try {
                            const parsed = JSON.parse(data);

                            if (parsed.conversation_id) {
                                // agent conversation id, not the session id —
                                // we discover the session id via sessionIndex
                                // after [DONE] below.
                                continue;
                            }

                            if (parsed.error) {
                                pendingText += parsed.error;
                                continue;
                            }

                            const text =
                                parsed.text ??
                                parsed.content ??
                                parsed.delta ??
                                '';
                            if (text) pendingText += text;
                        } catch {
                            const t = data.trim();
                            if (t && !t.startsWith('<')) {
                                pendingText += t;
                            }
                        }
                    }

                    if (pendingText) appendChunk(pendingText);
                }

                // Stream completed — if we started without a session, resolve
                // the newly-created session id via sessionIndex and bubble up.
                if (!hadSessionBeforeSend && onSessionCreated) {
                    try {
                        const indexUrl = sessionIndex.url({ book: bookId });
                        const res = await fetch(indexUrl, {
                            headers: jsonFetchHeaders(),
                        });
                        if (res.ok) {
                            const sessions: CoachSessionSummary[] =
                                await res.json();
                            const newest = sessions
                                .filter((s) => s.status === 'active')
                                .sort((a, b) =>
                                    b.updated_at.localeCompare(a.updated_at),
                                )[0];
                            if (newest) {
                                onSessionCreated(newest.id);
                            }
                        }
                    } catch {
                        // non-fatal — chat still works, just no id propagated.
                    }
                }
            } catch (err) {
                const aborted =
                    err instanceof DOMException && err.name === 'AbortError';
                if (!aborted) {
                    setStreamError(t('status.error.body'));
                    setMessages((prev) => {
                        if (
                            prev.length > 0 &&
                            prev[prev.length - 1].role === 'assistant' &&
                            prev[prev.length - 1].content === ''
                        ) {
                            return prev.slice(0, -1);
                        }
                        return prev;
                    });
                }
            } finally {
                isStreamingRef.current = false;
                setIsStreaming(false);
                abortRef.current = null;
            }
        },
        [bookId, sessionId, onSessionCreated, t],
    );

    const handleSend = useCallback(() => {
        void sendMessage(inputValueRef.current);
    }, [sendMessage]);

    const handleRetry = useCallback(() => {
        const last = lastSentMessageRef.current;
        if (!last) return;
        setStreamError(null);
        void sendMessage(last);
    }, [sendMessage]);

    const handleKeyDown = useCallback(
        (e: React.KeyboardEvent) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                handleSend();
            }
        },
        [handleSend],
    );

    const hasHistory = messages.length > 0;
    const showIntakeOpener = !hasHistory && !isLoadingHistory;

    return (
        <div className="flex min-h-0 flex-1 flex-col bg-surface">
            <div className="flex-1 overflow-y-auto">
                <div className="mx-auto flex w-full max-w-[720px] flex-col gap-6 px-6 py-10">
                    {isLoadingHistory && (
                        <div className="flex items-center justify-center gap-2 py-8">
                            <Loader
                                size={14}
                                className="animate-spin text-accent"
                            />
                        </div>
                    )}

                    {showIntakeOpener && (
                        <IntakeOpener
                            hello={t('intake.welcome.hello')}
                            body={t('intake.welcome.body')}
                        />
                    )}

                    {messages.map((msg, i) => {
                        const isLast = i === messages.length - 1;
                        const isLastAssistant =
                            msg.role === 'assistant' && isLast;
                        return msg.role === 'user' ? (
                            <UserBubble key={i} content={msg.content} />
                        ) : (
                            <AssistantRow
                                key={i}
                                content={msg.content}
                                streaming={isStreaming && isLastAssistant}
                                thinkingLabel={t('status.streaming')}
                            />
                        );
                    })}

                    {streamError && (
                        <ErrorCard
                            message={streamError}
                            retryLabel={t('status.error.retry')}
                            onRetry={handleRetry}
                        />
                    )}

                    <div ref={messagesEndRef} />
                </div>
            </div>

            {/* Input bar, sticks to bottom */}
            <div className="border-t border-border-light bg-surface">
                <div className="mx-auto w-full max-w-[720px] px-6 py-4">
                    <div className="relative">
                        <input
                            ref={inputRef}
                            type="text"
                            value={input}
                            onChange={(e) => setInput(e.target.value)}
                            onKeyDown={handleKeyDown}
                            placeholder={t('input.placeholder')}
                            aria-label={t('input.placeholder')}
                            disabled={isStreaming}
                            className="h-12 w-full rounded-xl border border-border-light bg-surface-card pr-14 pl-4 text-[14px] text-ink placeholder:text-ink-faint focus:ring-1 focus:ring-accent/40 focus:outline-none disabled:opacity-60"
                        />
                        <button
                            type="button"
                            onClick={handleSend}
                            disabled={!input.trim() || isStreaming}
                            aria-label={t('input.send')}
                            className="absolute top-1/2 right-1.5 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-lg bg-accent text-white transition-colors hover:bg-accent-dark disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <ArrowUp className="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

function IntakeOpener({ hello, body }: { hello: string; body: string }) {
    return (
        <div className="flex gap-3">
            <CoachAvatar />
            <div className="flex-1">
                <p className="text-[14px] leading-[1.55] font-medium text-ink">
                    {hello}
                </p>
                <p className="mt-1 text-[14px] leading-[1.55] text-ink-muted">
                    {body}
                </p>
            </div>
        </div>
    );
}

function UserBubble({ content }: { content: string }) {
    return (
        <div className="flex justify-end">
            <div className="max-w-[480px] rounded-xl border border-border-light bg-surface-card px-4 py-3 text-[14px] leading-[1.55] text-ink">
                <p className="whitespace-pre-wrap">{content}</p>
            </div>
        </div>
    );
}

function AssistantRow({
    content,
    streaming,
    thinkingLabel,
}: {
    content: string;
    streaming?: boolean;
    thinkingLabel: string;
}) {
    return (
        <div className="flex gap-3">
            <CoachAvatar />
            <div className="min-w-0 flex-1">
                {content ? (
                    <div className="flex items-start gap-1">
                        <AssistantMessage
                            content={content}
                            streaming={streaming}
                        />
                        {streaming && <StreamingDot />}
                    </div>
                ) : streaming ? (
                    <div className="flex items-center gap-[5px]">
                        <Loader
                            size={13}
                            className="animate-spin text-accent"
                        />
                        <span className="text-[12px] tracking-[0.01em] text-ink-muted">
                            {thinkingLabel}
                        </span>
                    </div>
                ) : null}
            </div>
        </div>
    );
}

function CoachAvatar() {
    return (
        <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-accent-light text-accent">
            <Sparkles className="h-3.5 w-3.5" />
        </div>
    );
}

function StreamingDot() {
    return (
        <span
            aria-hidden="true"
            className="ml-1 inline-block h-1.5 w-1.5 shrink-0 translate-y-[7px] animate-pulse rounded-full bg-accent"
        />
    );
}

function ErrorCard({
    message,
    retryLabel,
    onRetry,
}: {
    message: string;
    retryLabel: string;
    onRetry: () => void;
}) {
    return (
        <div className="flex items-start gap-3 rounded-lg border border-danger/30 bg-danger/5 px-4 py-3">
            <div className="flex-1 text-[13px] leading-[1.5] text-danger">
                {message}
            </div>
            <button
                type="button"
                onClick={onRetry}
                className="shrink-0 rounded-md border border-danger/30 px-2.5 py-1 text-[12px] font-medium text-danger transition-colors hover:bg-danger/10"
            >
                {retryLabel}
            </button>
        </div>
    );
}
