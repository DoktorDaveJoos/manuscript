import {
    ArrowUp,
    BookOpen,
    BookSearch,
    Loader,
    MessageCircle,
    RotateCcw,
    Sparkles,
} from 'lucide-react';
import MarkdownIt from 'markdown-it';
import { memo, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { chat } from '@/actions/App/Http/Controllers/AiController';
import {
    destroy as destroyConversation,
    messages as fetchConversationMessages,
} from '@/actions/App/Http/Controllers/AiConversationController';
import { chat as editorialChat } from '@/actions/App/Http/Controllers/EditorialReviewController';
import PanelHeader from '@/components/ui/PanelHeader';
import { severityDotColor } from '@/lib/editorial-constants';
import { extractErrorMessage, jsonFetchHeaders } from '@/lib/utils';
import type {
    Book,
    Chapter,
    Character,
    CharacterChapterPivot,
    EditorialSectionType,
    FindingSeverity,
} from '@/types/models';

const md = new MarkdownIt({ linkify: true, breaks: true });

type Message = {
    role: 'user' | 'assistant';
    content: string;
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
            className="ai-chat-markdown"
            dangerouslySetInnerHTML={{ __html: html }}
        />
    ) : (
        <div className="ai-chat-markdown whitespace-pre-wrap">{content}</div>
    );
});

function ContextStatus({ label }: { label: string }) {
    return (
        <div className="flex items-center gap-1">
            <span className="size-1 rounded-full bg-ai-green" />
            <span className="text-[11px] text-ink-muted">{label}</span>
        </div>
    );
}

function storageKey(
    bookId: number,
    chapterId: number | undefined,
    reviewId: number | undefined,
    sectionType: string | undefined,
    findingIndex: number | undefined,
): string {
    if (reviewId !== undefined) {
        return `manuscript:convo:review:${reviewId}:${sectionType ?? 'general'}:${findingIndex ?? 'none'}`;
    }
    return `manuscript:convo:book:${bookId}:ch:${chapterId ?? 'general'}`;
}

type ChapterWithCharacters = Chapter & {
    characters?: (Character & { pivot: CharacterChapterPivot })[];
};

export default function AiChatDrawer({
    book,
    chapter,
    onClose,
    title,
    editorialReview,
}: {
    book: Book;
    chapter?: ChapterWithCharacters;
    onClose: () => void;
    title?: string;
    editorialReview?: {
        reviewId: number;
        sectionType?: EditorialSectionType;
        findingIndex?: number;
        findingDescription?: string;
        findingSeverity?: FindingSeverity;
        sectionLabel?: string;
    };
}) {
    const { t } = useTranslation('ai');
    const [messages, setMessages] = useState<Message[]>([]);
    const [input, setInput] = useState('');
    const [isStreaming, setIsStreaming] = useState(false);
    const isStreamingRef = useRef(false);
    const [isLoadingHistory, setIsLoadingHistory] = useState(false);
    const messagesEndRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const conversationIdRef = useRef<string | null>(null);

    const lsKey = useMemo(
        () =>
            storageKey(
                book.id,
                chapter?.id,
                editorialReview?.reviewId,
                editorialReview?.sectionType,
                editorialReview?.findingIndex,
            ),
        [
            book.id,
            chapter?.id,
            editorialReview?.reviewId,
            editorialReview?.sectionType,
            editorialReview?.findingIndex,
        ],
    );

    useEffect(() => {
        const savedId = localStorage.getItem(lsKey);
        if (!savedId) return;

        const controller = new AbortController();
        setIsLoadingHistory(true);
        conversationIdRef.current = savedId;

        const url = fetchConversationMessages.url({
            book: book.id,
            conversation: savedId,
        });

        fetch(url, { headers: jsonFetchHeaders(), signal: controller.signal })
            .then((res) => {
                if (!res.ok) throw new Error('Not found');
                return res.json();
            })
            .then((data: Message[]) => {
                if (data.length > 0) {
                    setMessages(data);
                }
            })
            .catch((err) => {
                if (err.name === 'AbortError') return;
                localStorage.removeItem(lsKey);
                conversationIdRef.current = null;
            })
            .finally(() => setIsLoadingHistory(false));

        return () => controller.abort();
    }, [lsKey, book.id]);

    useEffect(() => {
        inputRef.current?.focus();
    }, []);

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    const inputValueRef = useRef(input);
    inputValueRef.current = input;

    const handleSend = useCallback(async () => {
        const trimmed = inputValueRef.current.trim();
        if (!trimmed || isStreamingRef.current) return;

        setInput('');
        setMessages((prev) => [
            ...prev,
            { role: 'user', content: trimmed },
            { role: 'assistant', content: '' },
        ]);
        isStreamingRef.current = true;
        setIsStreaming(true);

        try {
            const chatUrl = editorialReview
                ? editorialChat.url({
                      book: book.id,
                      review: editorialReview.reviewId,
                  })
                : chat.url(book.id);

            const body: Record<string, unknown> = {
                message: trimmed,
                conversation_id: conversationIdRef.current ?? undefined,
            };

            if (editorialReview) {
                if (editorialReview.sectionType) {
                    body.section_type = editorialReview.sectionType;
                }
                if (editorialReview.findingIndex !== undefined) {
                    body.finding_index = editorialReview.findingIndex;
                }
            } else if (chapter) {
                body.chapter_id = chapter.id;
            }

            const response = await fetch(chatUrl, {
                method: 'POST',
                headers: {
                    ...jsonFetchHeaders(),
                    Accept: 'text/event-stream',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body),
            });

            if (!response.ok) {
                const errorText = await response.text().catch(() => '');
                const errorMessage = extractErrorMessage(
                    errorText,
                    t('chat.requestFailed'),
                );
                setMessages((prev) => {
                    const updated = [...prev];
                    updated[updated.length - 1] = {
                        role: 'assistant',
                        content: errorMessage,
                    };
                    return updated;
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
                    if (line.startsWith('data: ')) {
                        const data = line.slice(6);
                        if (data === '[DONE]') continue;
                        try {
                            const parsed = JSON.parse(data);

                            if (
                                parsed.conversation_id &&
                                !conversationIdRef.current
                            ) {
                                conversationIdRef.current =
                                    parsed.conversation_id;
                                localStorage.setItem(
                                    lsKey,
                                    parsed.conversation_id,
                                );
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
                            const trimmed = data.trim();
                            if (trimmed && !trimmed.startsWith('<')) {
                                pendingText += trimmed;
                            }
                        }
                    }
                }

                if (pendingText) appendChunk(pendingText);
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
            isStreamingRef.current = false;
            setIsStreaming(false);
        }
    }, [book.id, chapter?.id, editorialReview, lsKey, t]);

    const handleReset = useCallback(() => {
        const currentId = conversationIdRef.current;

        if (currentId) {
            const url = destroyConversation.url({
                book: book.id,
                conversation: currentId,
            });
            fetch(url, {
                method: 'DELETE',
                headers: jsonFetchHeaders(),
            }).catch(() => {});
        }

        localStorage.removeItem(lsKey);
        conversationIdRef.current = null;
        setMessages([]);
        setInput('');
        inputRef.current?.focus();
    }, [book.id, lsKey]);

    const handleKeyDown = useCallback(
        (e: React.KeyboardEvent) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                handleSend();
            }
        },
        [handleSend],
    );

    const characterNames = chapter
        ? (chapter.characters ?? []).map((c) => c.name).slice(0, 3)
        : [];
    const wordCount = chapter?.word_count ?? 0;

    return (
        <aside className="flex h-full shrink-0 flex-col border-l border-border-light bg-surface-sidebar">
            <PanelHeader
                title={title ?? t('askAi')}
                icon={<MessageCircle size={14} className="text-ink-muted" />}
                onClose={onClose}
                suffix={
                    messages.length > 0 && !isStreaming ? (
                        <button
                            type="button"
                            onClick={handleReset}
                            className="flex size-6 items-center justify-center rounded text-ink-faint transition-colors hover:text-ink"
                            title={t('chat.newConversation')}
                        >
                            <RotateCcw size={13} />
                        </button>
                    ) : undefined
                }
            />

            {/* Chapter Context */}
            {chapter && (
                <div className="flex flex-col gap-1 border-b border-border-light px-5 py-2.5">
                    <div className="flex items-center gap-2">
                        <span className="size-[5px] shrink-0 rounded-full bg-ai-green" />
                        <BookOpen size={14} className="shrink-0 text-accent" />
                        <span className="truncate text-xs font-medium text-ink">
                            {chapter.title || 'Untitled'}
                        </span>
                        <span className="shrink-0 rounded bg-neutral-bg px-1.5 py-0.5 text-[11px] font-medium text-ink-muted">
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
                        <ContextStatus label={t('chat.chapterLoaded')} />
                        <ContextStatus label={t('chat.bookContext')} />
                    </div>
                </div>
            )}

            {/* Editorial Review Context */}
            {editorialReview && !chapter && (
                <div className="flex flex-col gap-1 border-b border-border-light px-5 py-2.5">
                    {editorialReview.findingDescription &&
                    editorialReview.findingSeverity ? (
                        <>
                            <div className="flex items-center gap-2">
                                <span
                                    className={`size-[5px] shrink-0 rounded-full ${severityDotColor[editorialReview.findingSeverity]}`}
                                />
                                <BookSearch
                                    size={14}
                                    className="shrink-0 text-accent"
                                />
                                <span className="truncate text-xs font-medium text-ink">
                                    {editorialReview.sectionLabel}
                                </span>
                                <span className="shrink-0 rounded bg-neutral-bg px-1.5 py-0.5 text-[11px] font-medium text-ink-muted">
                                    {t(
                                        `editorial-review:severity.${editorialReview.findingSeverity}`,
                                    )}
                                </span>
                            </div>
                            <p className="line-clamp-3 text-[11px] text-ink-faint">
                                {editorialReview.findingDescription}
                            </p>
                            <div className="flex items-center gap-3">
                                <ContextStatus
                                    label={t('chat.findingLoaded')}
                                />
                                <ContextStatus label={t('chat.bookContext')} />
                            </div>
                        </>
                    ) : (
                        <>
                            <div className="flex items-center gap-2">
                                <span className="size-[5px] shrink-0 rounded-full bg-ai-green" />
                                <Sparkles
                                    size={14}
                                    className="shrink-0 text-accent"
                                />
                                <span className="truncate text-xs font-medium text-ink">
                                    {t(
                                        'editorial-review:sectionLabel.editorialReview',
                                    )}
                                </span>
                            </div>
                            <ContextStatus label={t('chat.bookContext')} />
                        </>
                    )}
                </div>
            )}

            {/* Messages */}
            <div className="flex flex-1 flex-col gap-4 overflow-y-auto p-5">
                {isLoadingHistory && messages.length === 0 && (
                    <div className="flex items-center justify-center gap-2 py-4">
                        <Loader
                            size={14}
                            className="animate-spin text-accent"
                        />
                        <span className="text-xs text-ink-muted">
                            {t('chat.loadingHistory')}
                        </span>
                    </div>
                )}
                {!isLoadingHistory && messages.length === 0 && (
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
                                    <AssistantMessage
                                        content={msg.content}
                                        streaming={
                                            isStreaming &&
                                            i === messages.length - 1
                                        }
                                    />
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
