import {
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
import AiChatInput from '@/components/ui/AiChatInput';
import type { AiChatInputHandle } from '@/components/ui/AiChatInput';
import { Bubble, BubbleContent } from '@/components/ui/bubble';
import Button from '@/components/ui/Button';
import { Marker, MarkerContent, MarkerIcon } from '@/components/ui/marker';
import {
    Message,
    MessageAvatar,
    MessageContent,
    MessageFooter,
    MessageHeader,
} from '@/components/ui/message';
import {
    MessageScroller,
    MessageScrollerButton,
    MessageScrollerComposer,
    MessageScrollerContent,
    MessageScrollerItem,
    MessageScrollerProvider,
    MessageScrollerViewport,
} from '@/components/ui/message-scroller';
import PanelHeader from '@/components/ui/PanelHeader';
import { useAiErrorToast } from '@/hooks/useAiErrorToast';
import type { AiErrorPayload } from '@/hooks/useAiErrorToast';
import { track } from '@/lib/analytics';
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
    const showAiErrorToast = useAiErrorToast();
    const [messages, setMessages] = useState<Message[]>([]);
    const [input, setInput] = useState('');
    const [isStreaming, setIsStreaming] = useState(false);
    const isStreamingRef = useRef(false);
    const [isLoadingHistory, setIsLoadingHistory] = useState(false);
    const inputRef = useRef<AiChatInputHandle>(null);
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

    const inputValueRef = useRef(input);
    inputValueRef.current = input;

    const handleSend = useCallback(async () => {
        const trimmed = inputValueRef.current.trim();
        if (!trimmed || isStreamingRef.current) return;

        track('ai_feature_used', {
            type: editorialReview ? 'editorial' : 'chat',
        });
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
                let errorPayload: AiErrorPayload = {
                    kind: 'unknown',
                    message: errorMessage,
                };

                try {
                    const parsed = JSON.parse(errorText) as AiErrorPayload;
                    errorPayload = {
                        kind: parsed.kind ?? 'unknown',
                        message: parsed.message ?? errorMessage,
                        provider: parsed.provider,
                    };
                } catch {
                    // Non-JSON responses use the safe fallback message above.
                }

                showAiErrorToast(errorPayload);
                const visibleErrorMessage =
                    errorPayload.kind === 'no_provider'
                        ? (errorPayload.message ?? t('chat.requestFailed'))
                        : t(`error.toast.${errorPayload.kind}.title`, {
                              defaultValue: t('chat.requestFailed'),
                          });
                setMessages((prev) => {
                    const updated = [...prev];
                    updated[updated.length - 1] = {
                        role: 'assistant',
                        content: visibleErrorMessage,
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
                                const kind = parsed.kind ?? 'unknown';
                                showAiErrorToast({
                                    kind,
                                    message: parsed.error,
                                    provider: parsed.provider,
                                });
                                pendingText += t(`error.toast.${kind}.title`, {
                                    defaultValue: t('chat.requestFailed'),
                                });
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
    }, [book.id, chapter, editorialReview, lsKey, showAiErrorToast, t]);

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

    const characterNames = chapter
        ? (chapter.characters ?? []).map((c) => c.name).slice(0, 3)
        : [];
    const wordCount = chapter?.word_count ?? 0;

    return (
        <aside
            className="flex h-full shrink-0 flex-col border-l border-border-light bg-surface-sidebar"
            data-testid="ai-chat-surface"
        >
            <PanelHeader
                title={title ?? t('askAi')}
                icon={<MessageCircle size={14} className="text-ink-muted" />}
                onClose={onClose}
                suffix={
                    messages.length > 0 && !isStreaming ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={handleReset}
                            className="size-6 rounded text-ink-faint hover:text-ink"
                            title={t('chat.newConversation')}
                            aria-label={t('chat.newConversation')}
                        >
                            <RotateCcw size={14} />
                        </Button>
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
                            {chapter.title || t('chat.untitledChapter')}
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
            <div className="min-h-0 flex-1">
                <MessageScrollerProvider
                    autoScroll
                    defaultScrollPosition="end"
                    scrollPreviousItemPeek={24}
                >
                    <MessageScroller
                        canvas="panel"
                        data-testid="ai-chat-message-scroller"
                    >
                        <MessageScrollerViewport>
                            <MessageScrollerContent
                                aria-busy={isStreaming}
                                className="gap-4 p-5"
                            >
                                {isLoadingHistory && messages.length === 0 && (
                                    <MessageScrollerItem>
                                        <Marker className="justify-center py-4">
                                            <MarkerIcon>
                                                <Loader className="animate-spin text-accent" />
                                            </MarkerIcon>
                                            <MarkerContent>
                                                {t('chat.loadingHistory')}
                                            </MarkerContent>
                                        </Marker>
                                    </MessageScrollerItem>
                                )}
                                {!isLoadingHistory && messages.length === 0 && (
                                    <MessageScrollerItem>
                                        <Marker className="justify-center text-center">
                                            <MarkerContent>
                                                {t('chat.emptyState')}
                                            </MarkerContent>
                                        </Marker>
                                    </MessageScrollerItem>
                                )}
                                {messages.map((msg, i) => (
                                    <MessageScrollerItem
                                        key={i}
                                        messageId={`message-${i}`}
                                        scrollAnchor={msg.role === 'user'}
                                    >
                                        {msg.role === 'user' ? (
                                            <Message
                                                align="end"
                                                data-message-role="user"
                                            >
                                                <MessageContent>
                                                    <MessageHeader>
                                                        {t('chat.senderYou')}
                                                    </MessageHeader>
                                                    <Bubble variant="muted">
                                                        <BubbleContent>
                                                            {msg.content}
                                                        </BubbleContent>
                                                    </Bubble>
                                                </MessageContent>
                                            </Message>
                                        ) : (
                                            <Message data-message-role="assistant">
                                                <MessageAvatar
                                                    aria-label={t(
                                                        'chat.senderAi',
                                                    )}
                                                >
                                                    <Sparkles className="size-3.5 text-accent" />
                                                </MessageAvatar>
                                                <MessageContent>
                                                    <MessageHeader>
                                                        {t('chat.senderAi')}
                                                    </MessageHeader>
                                                    <Bubble variant="secondary">
                                                        <BubbleContent>
                                                            {msg.content ? (
                                                                <AssistantMessage
                                                                    content={
                                                                        msg.content
                                                                    }
                                                                    streaming={
                                                                        isStreaming &&
                                                                        i ===
                                                                            messages.length -
                                                                                1
                                                                    }
                                                                />
                                                            ) : isStreaming &&
                                                              i ===
                                                                  messages.length -
                                                                      1 ? (
                                                                <Marker>
                                                                    <MarkerIcon>
                                                                        <Loader className="animate-spin text-accent" />
                                                                    </MarkerIcon>
                                                                    <MarkerContent>
                                                                        {t(
                                                                            'chat.thinking',
                                                                        )}
                                                                    </MarkerContent>
                                                                </Marker>
                                                            ) : null}
                                                        </BubbleContent>
                                                    </Bubble>
                                                    {isStreaming &&
                                                        i ===
                                                            messages.length -
                                                                1 &&
                                                        msg.content && (
                                                            <MessageFooter>
                                                                <Marker>
                                                                    <MarkerIcon>
                                                                        <Loader className="animate-spin text-accent" />
                                                                    </MarkerIcon>
                                                                    <MarkerContent>
                                                                        {t(
                                                                            'chat.thinking',
                                                                        )}
                                                                    </MarkerContent>
                                                                </Marker>
                                                            </MessageFooter>
                                                        )}
                                                </MessageContent>
                                            </Message>
                                        )}
                                    </MessageScrollerItem>
                                ))}
                            </MessageScrollerContent>
                        </MessageScrollerViewport>
                        <MessageScrollerButton />
                        <MessageScrollerComposer
                            data-testid="ai-chat-composer"
                            className="px-5 py-3"
                        >
                            <AiChatInput
                                ref={inputRef}
                                value={input}
                                onChange={setInput}
                                onSend={handleSend}
                                placeholder={t('chat.placeholder')}
                                ariaLabel={t('chat.placeholder')}
                                disabled={isStreaming}
                                className="pointer-events-auto"
                            />
                        </MessageScrollerComposer>
                    </MessageScroller>
                </MessageScrollerProvider>
            </div>
        </aside>
    );
}
