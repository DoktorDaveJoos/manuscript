import { ArrowUp, Loader, Sparkles } from 'lucide-react';
import {
    forwardRef,
    memo,
    useCallback,
    useEffect,
    useImperativeHandle,
    useMemo,
    useRef,
    useState,
} from 'react';
import { useTranslation } from 'react-i18next';
import {
    sessionIndex,
    sessionShow,
    stream,
} from '@/actions/App/Http/Controllers/PlotCoachController';
import BatchProposalCard from '@/components/plot/BatchProposalCard';
import type {
    BatchWrite,
    ProposalState,
} from '@/components/plot/BatchProposalCard';
import { Alert, AlertDescription } from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import Textarea from '@/components/ui/Textarea';
import { useAiErrorToast } from '@/hooks/useAiErrorToast';
import { md } from '@/lib/markdown';
import { extractErrorMessage, jsonFetchHeaders } from '@/lib/utils';

const SENTINEL_OPEN = '<!-- PLOT_COACH_BATCH_PROPOSAL';
const SENTINEL_CLOSE = '-->';

/**
 * Matches the internal wire signals emitted by approval-card buttons. These
 * are not human-typed input — the card press IS the UI — so we never render
 * them as user bubbles.
 */
const WIRE_SIGNAL_RE =
    /^(APPROVE:batch:[0-9a-f-]{36}|CANCEL:batch:[0-9a-f-]{36}|UNDO:proposal:[0-9a-f-]{36}|UNDO:last)$/i;

/**
 * Matches a PLOT_COACH_BATCH_PROPOSAL sentinel block in an assistant message.
 * Global flag so we can scan for every occurrence — the model occasionally
 * mimics a fake sentinel before the real tool output arrives; we pick the
 * last one because that's the tool's append.
 */
const SENTINEL_RE_GLOBAL = /<!-- PLOT_COACH_BATCH_PROPOSAL\n([\s\S]*?)\n-->/g;

/**
 * A mimicked preview block the model sometimes writes verbatim. Used to strip
 * noise from the preamble when the model echoes the tool output. Matches from
 * `## Proposed batch` or `## Proposed chapter plan` up to (but not including)
 * the next sentinel opener or end of string.
 */
const MIMICKED_PREVIEW_RE =
    /\n*##\s+Proposed (batch|chapter plan)[\s\S]*?(?=<!-- PLOT_COACH_BATCH_PROPOSAL|$)/;

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
    pending_board_changes?: unknown[];
    proposal_states?: Record<string, ProposalState>;
};

type ParsedProposal = {
    preamble: string;
    proposal: {
        proposalId: string;
        writes: BatchWrite[];
        summary: string;
    } | null;
    postscript: string;
    /** Sentinel opened but close not yet seen — buffer without rendering. */
    streamingSentinel: boolean;
};

/**
 * Extract a PLOT_COACH_BATCH_PROPOSAL sentinel from an assistant message.
 *
 * Behaviour:
 * - Picks the LAST parseable sentinel in the message. The model occasionally
 *   writes a mimicked preview with a hallucinated proposal_id before the
 *   real tool output arrives; the real tool output is always appended last,
 *   so last-wins guarantees the uuid on the card matches one the server
 *   persisted.
 * - Discards any content between the first sentinel opener and the last
 *   sentinel closer (that span is either noise from a mimicked preview or an
 *   earlier hallucinated block).
 * - Strips a mimicked `## Proposed batch`/`## Proposed chapter plan` block
 *   from the preamble so the model's paraphrase of the tool output doesn't
 *   render alongside the card.
 * - During streaming, if a sentinel opener has been emitted but no closer yet,
 *   we buffer so malformed JSON never flashes.
 */
export function parseAssistantMessage(content: string): ParsedProposal {
    const firstOpenIdx = content.indexOf(SENTINEL_OPEN);
    if (firstOpenIdx === -1) {
        return {
            preamble: stripMimickedPreview(content),
            proposal: null,
            postscript: '',
            streamingSentinel: false,
        };
    }

    // Collect every complete sentinel and pick the last parseable one.
    const matches: Array<{
        match: RegExpExecArray;
        parsed: ParsedProposal['proposal'];
    }> = [];
    SENTINEL_RE_GLOBAL.lastIndex = 0;
    let m: RegExpExecArray | null;
    while ((m = SENTINEL_RE_GLOBAL.exec(content)) !== null) {
        const parsed = tryParseSentinelJson(m[1]);
        matches.push({ match: m, parsed });
    }

    if (matches.length === 0) {
        // Opener is present but no closer yet — sentinel mid-stream.
        return {
            preamble: stripMimickedPreview(content.slice(0, firstOpenIdx)),
            proposal: null,
            postscript: '',
            streamingSentinel: true,
        };
    }

    // Prefer the last parseable match; fall back to the last match overall.
    let chosen = matches[matches.length - 1];
    for (let i = matches.length - 1; i >= 0; i--) {
        if (matches[i].parsed) {
            chosen = matches[i];
            break;
        }
    }

    const preamble = stripMimickedPreview(content.slice(0, firstOpenIdx));
    const postscript = content.slice(
        chosen.match.index + chosen.match[0].length,
    );

    return {
        preamble,
        proposal: chosen.parsed,
        postscript,
        streamingSentinel: false,
    };
}

/**
 * Merge server-reported proposal states into local React state without firing
 * a re-render when nothing changed. Optimistic local clicks beat the server
 * briefly, so we skip keys whose state is already identical.
 */
/**
 * Parse a string as JSON without throwing. Used to peek at structured
 * error responses (`{kind, message, provider}`) before falling back to
 * plain-text rendering.
 */
function safeJson(text: string): Record<string, unknown> | null {
    if (!text) return null;
    try {
        const parsed = JSON.parse(text);
        return typeof parsed === 'object' && parsed !== null
            ? (parsed as Record<string, unknown>)
            : null;
    } catch {
        return null;
    }
}

function mergeProposalStates(
    setter: React.Dispatch<React.SetStateAction<Record<string, ProposalState>>>,
    incoming: Record<string, ProposalState>,
): void {
    setter((prev) => {
        let changed = false;
        const next = { ...prev };
        for (const [id, state] of Object.entries(incoming)) {
            if (next[id] !== state) {
                next[id] = state;
                changed = true;
            }
        }
        return changed ? next : prev;
    });
}

function tryParseSentinelJson(body: string): ParsedProposal['proposal'] {
    try {
        const parsed = JSON.parse(body);
        if (
            parsed &&
            typeof parsed === 'object' &&
            typeof parsed.proposal_id === 'string' &&
            Array.isArray(parsed.writes) &&
            typeof parsed.summary === 'string'
        ) {
            return {
                proposalId: parsed.proposal_id,
                writes: parsed.writes as BatchWrite[],
                summary: parsed.summary,
            };
        }
    } catch {
        // fall through
    }
    return null;
}

function stripMimickedPreview(text: string): string {
    return text.replace(MIMICKED_PREVIEW_RE, '').replace(/\s+$/g, '');
}

const AssistantMessage = memo(function AssistantMessage({
    content,
}: {
    content: string;
}) {
    const html = useMemo(() => md.render(content), [content]);
    return (
        <div
            className="ai-chat-markdown text-sm leading-[1.55] text-ink"
            dangerouslySetInnerHTML={{ __html: html }}
        />
    );
});

export type ChatSurfaceHandle = {
    /**
     * Send a message into the stream as if the user typed it. Used by the
     * plot page's "Undo last batch" button to dispatch the "UNDO:last"
     * conversational signal without the user typing it.
     */
    sendSystemSignal: (message: string) => void;
};

/**
 * Plot Coach chat surface. Renders hydrated history + streaming live
 * responses, and kicks off the first stream (which creates the session
 * server-side) when `sessionId` is null.
 */
const ChatSurface = forwardRef<ChatSurfaceHandle, ChatSurfaceProps>(
    function ChatSurface({ bookId, sessionId, onSessionCreated }, ref) {
        const { t } = useTranslation('plot-coach');
        const [messages, setMessages] = useState<ChatMessage[]>([]);
        const [input, setInput] = useState('');
        const [isStreaming, setIsStreaming] = useState(false);
        const [isLoadingHistory, setIsLoadingHistory] = useState(false);
        const [streamError, setStreamError] = useState<string | null>(null);
        const showAiErrorToast = useAiErrorToast();
        const [pendingBoardChanges, setPendingBoardChanges] =
            useState<number>(0);
        const [proposalStates, setProposalStates] = useState<
            Record<string, ProposalState>
        >({});
        const [currentToolName, setCurrentToolName] = useState<string | null>(
            null,
        );

        const isStreamingRef = useRef(false);
        // Inflight tool calls keyed by tool_id → tool_name. The latest still
        // inflight is what we surface in the status line.
        const inflightToolsRef = useRef<Map<string, string>>(new Map());
        // Suppresses sub-threshold flicker for tools that complete quickly:
        // we delay revealing the status text until the call is still inflight
        // after this window.
        const toolStatusTimerRef = useRef<number | null>(null);
        // Mirrors `currentToolName` so callbacks can read the latest value
        // without subscribing to state changes.
        const currentToolNameRef = useRef<string | null>(null);
        const inputValueRef = useRef(input);
        inputValueRef.current = input;

        const messagesEndRef = useRef<HTMLDivElement>(null);
        const inputRef = useRef<HTMLTextAreaElement>(null);
        const abortRef = useRef<AbortController | null>(null);
        const lastSentMessageRef = useRef<string | null>(null);

        // Auto-grow textarea height with content, capped to ~6 lines.
        useEffect(() => {
            const el = inputRef.current;
            if (!el) return;
            el.style.height = 'auto';
            const max = 160;
            el.style.height = `${Math.min(el.scrollHeight, max)}px`;
        }, [input]);

        // Hydrate history for an existing session. Abort on unmount / id change.
        useEffect(() => {
            if (sessionId === null || sessionId === undefined) {
                setMessages([]);
                setPendingBoardChanges(0);
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
                    const changes = Array.isArray(data.pending_board_changes)
                        ? data.pending_board_changes.length
                        : 0;
                    setPendingBoardChanges(changes);
                    setProposalStates(data.proposal_states ?? {});
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

        // Abort any in-flight stream + clear the tool-status delay timer on
        // unmount.
        useEffect(() => {
            return () => {
                abortRef.current?.abort();
                if (toolStatusTimerRef.current !== null) {
                    window.clearTimeout(toolStatusTimerRef.current);
                    toolStatusTimerRef.current = null;
                }
            };
        }, []);

        // Poll pending_board_changes every 10s while idle (not streaming).
        useEffect(() => {
            if (sessionId === null || sessionId === undefined) return;
            if (isStreaming) return;

            const interval = window.setInterval(() => {
                const url = sessionShow.url({
                    book: bookId,
                    session: sessionId,
                });
                fetch(url, { headers: jsonFetchHeaders() })
                    .then((res) => (res.ok ? res.json() : null))
                    .then((data: CoachSessionShowResponse | null) => {
                        if (!data) return;
                        const count = Array.isArray(data.pending_board_changes)
                            ? data.pending_board_changes.length
                            : 0;
                        setPendingBoardChanges((prev) =>
                            prev === count ? prev : count,
                        );
                        if (data.proposal_states) {
                            mergeProposalStates(
                                setProposalStates,
                                data.proposal_states,
                            );
                        }
                    })
                    .catch(() => {
                        // non-fatal
                    });
            }, 10_000);

            return () => window.clearInterval(interval);
        }, [bookId, sessionId, isStreaming]);

        const clearToolStatusTimer = useCallback(() => {
            if (toolStatusTimerRef.current !== null) {
                window.clearTimeout(toolStatusTimerRef.current);
                toolStatusTimerRef.current = null;
            }
        }, []);

        const writeCurrentToolName = useCallback((next: string | null) => {
            currentToolNameRef.current = next;
            setCurrentToolName(next);
        }, []);

        const refreshToolStatus = useCallback(() => {
            const inflight = inflightToolsRef.current;
            if (inflight.size === 0) {
                clearToolStatusTimer();
                writeCurrentToolName(null);
                return;
            }
            // Latest insertion wins — JS Maps preserve insertion order, so the
            // most recent tool_call is at the tail.
            let latest: string | null = null;
            for (const name of inflight.values()) latest = name;
            writeCurrentToolName(latest);
        }, [clearToolStatusTimer, writeCurrentToolName]);

        const handleToolCall = useCallback(
            (toolId: string, toolName: string) => {
                // Tools whose UI is the approval card itself never surface a
                // status line — the card already telegraphs what's happening.
                if (
                    toolName === 'ProposeBatch' ||
                    toolName === 'ProposeChapterPlan'
                ) {
                    return;
                }

                inflightToolsRef.current.set(toolId, toolName);

                // Already showing a label → swap immediately and let any
                // pending grace timer fall away (its work is now redundant).
                if (currentToolNameRef.current !== null) {
                    clearToolStatusTimer();
                    writeCurrentToolName(toolName);
                    return;
                }

                // First inflight tool of a quiet stream → wait the grace
                // window so fast tools never flash a label.
                if (toolStatusTimerRef.current !== null) return;

                toolStatusTimerRef.current = window.setTimeout(() => {
                    toolStatusTimerRef.current = null;
                    refreshToolStatus();
                }, 250);
            },
            [clearToolStatusTimer, refreshToolStatus, writeCurrentToolName],
        );

        const handleToolResult = useCallback(
            (toolId: string) => {
                inflightToolsRef.current.delete(toolId);
                refreshToolStatus();
            },
            [refreshToolStatus],
        );

        const resetToolTracking = useCallback(() => {
            inflightToolsRef.current.clear();
            clearToolStatusTimer();
            writeCurrentToolName(null);
        }, [clearToolStatusTimer, writeCurrentToolName]);

        const sendMessage = useCallback(
            async (rawMessage: string) => {
                const trimmed = rawMessage.trim();
                if (!trimmed || isStreamingRef.current) return;

                setStreamError(null);
                setInput('');
                lastSentMessageRef.current = trimmed;
                resetToolTracking();

                const hadSessionBeforeSend = sessionId !== null;

                // Approval card buttons send bare wire signals (APPROVE:batch /
                // CANCEL:batch / UNDO:last). Those aren't something the user
                // typed — the card itself is the UI for them — so we skip the
                // user bubble entirely and go straight to the assistant turn.
                const isWireSignal = WIRE_SIGNAL_RE.test(trimmed);

                setMessages((prev) => [
                    ...prev,
                    ...(isWireSignal
                        ? []
                        : [{ role: 'user' as const, content: trimmed }]),
                    { role: 'assistant' as const, content: '' },
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
                        // Backend returns {message, kind, provider} on AI
                        // errors. If we can parse a kind, show the toast and
                        // reuse parsed.message for the inline UI; otherwise
                        // let extractErrorMessage do its own parsing pass.
                        const parsed = safeJson(errorText);
                        const errorMessage =
                            parsed && typeof parsed.message === 'string'
                                ? parsed.message
                                : extractErrorMessage(
                                      errorText,
                                      t('status.error.body'),
                                  );
                        if (parsed && typeof parsed.kind === 'string') {
                            showAiErrorToast({
                                kind: parsed.kind,
                                message:
                                    typeof parsed.message === 'string'
                                        ? parsed.message
                                        : null,
                                provider:
                                    typeof parsed.provider === 'string'
                                        ? parsed.provider
                                        : null,
                            });
                        }
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
                                    if (typeof parsed.kind === 'string') {
                                        showAiErrorToast({
                                            kind: parsed.kind,
                                            message: parsed.error,
                                            provider: parsed.provider,
                                        });
                                    } else {
                                        pendingText += parsed.error;
                                    }
                                    continue;
                                }

                                // Track inflight tool calls so we can surface
                                // a humanized status line ("Re-reading the
                                // prose…"). tool_call carries no text — done
                                // after dispatching.
                                if (
                                    parsed.type === 'tool_call' &&
                                    typeof parsed.tool_id === 'string' &&
                                    typeof parsed.tool_name === 'string'
                                ) {
                                    handleToolCall(
                                        parsed.tool_id,
                                        parsed.tool_name,
                                    );
                                    continue;
                                }

                                // tool_result clears the inflight entry. We
                                // fall through afterwards so ProposeBatch /
                                // ProposeChapterPlan can still splice their
                                // preview into the message stream below.
                                if (
                                    parsed.type === 'tool_result' &&
                                    typeof parsed.tool_id === 'string'
                                ) {
                                    handleToolResult(parsed.tool_id);
                                }

                                // ProposeBatch / ProposeChapterPlan emit their
                                // preview + sentinel in the tool `result` —
                                // splice it into the assistant's text stream so
                                // the BatchProposalCard renders. Without this,
                                // a well-behaved agent that doesn't paraphrase
                                // its own tool output has no visible card.
                                if (
                                    parsed.type === 'tool_result' &&
                                    parsed.successful !== false &&
                                    (parsed.tool_name === 'ProposeBatch' ||
                                        parsed.tool_name ===
                                            'ProposeChapterPlan') &&
                                    typeof parsed.result === 'string' &&
                                    parsed.result !== ''
                                ) {
                                    pendingText += '\n\n' + parsed.result;
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

                    // Stream completed — clear pending board changes locally
                    // (server clears them post-stream too) and resolve the new
                    // session id if we created one.
                    setPendingBoardChanges(0);

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
                                        b.updated_at.localeCompare(
                                            a.updated_at,
                                        ),
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
                        err instanceof DOMException &&
                        err.name === 'AbortError';
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
                    resetToolTracking();
                }
            },
            [
                bookId,
                sessionId,
                onSessionCreated,
                t,
                handleToolCall,
                handleToolResult,
                resetToolTracking,
            ],
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

        const setProposalState = useCallback(
            (id: string, state: ProposalState) => {
                setProposalStates((prev) =>
                    prev[id] === state ? prev : { ...prev, [id]: state },
                );
            },
            [],
        );

        const handleBatchApprove = useCallback(
            (proposalId: string) => {
                setProposalState(proposalId, 'approved');
                void sendMessage(`APPROVE:batch:${proposalId}`);
            },
            [setProposalState, sendMessage],
        );

        const handleBatchCancel = useCallback(
            (proposalId: string) => {
                setProposalState(proposalId, 'cancelled');
                void sendMessage(`CANCEL:batch:${proposalId}`);
            },
            [setProposalState, sendMessage],
        );

        const handleBatchUndo = useCallback(
            (proposalId: string) => {
                setProposalState(proposalId, 'reverted');
                void sendMessage(`UNDO:proposal:${proposalId}`);
            },
            [setProposalState, sendMessage],
        );

        useImperativeHandle(
            ref,
            () => ({
                sendSystemSignal: (message: string) => {
                    void sendMessage(message);
                },
            }),
            [sendMessage],
        );

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

        // Index of the most recent assistant message holding a still-pending
        // proposal. Older pending proposals render dimmed so the author isn't
        // tempted to approve a superseded one.
        const latestActiveProposalIndex = useMemo(() => {
            for (let i = messages.length - 1; i >= 0; i--) {
                const m = messages[i];
                if (m.role !== 'assistant') continue;
                const parsed = parseAssistantMessage(m.content);
                if (!parsed.proposal) continue;
                const state =
                    proposalStates[parsed.proposal.proposalId] ?? 'pending';
                if (state === 'pending') {
                    return i;
                }
            }
            return -1;
        }, [messages, proposalStates]);

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
                            if (msg.role === 'user') {
                                return (
                                    <UserBubble key={i} content={msg.content} />
                                );
                            }
                            return (
                                <AssistantRow
                                    key={i}
                                    content={msg.content}
                                    streaming={isStreaming && isLastAssistant}
                                    thinkingLabel={t('status.streaming')}
                                    toolStatusLabel={
                                        isLastAssistant && currentToolName
                                            ? t(
                                                  `status.tool.${currentToolName}`,
                                                  {
                                                      defaultValue:
                                                          t('status.streaming'),
                                                  },
                                              )
                                            : null
                                    }
                                    isActiveProposalRow={
                                        i === latestActiveProposalIndex
                                    }
                                    proposalStates={proposalStates}
                                    onApprove={handleBatchApprove}
                                    onCancel={handleBatchCancel}
                                    onUndo={handleBatchUndo}
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

                {/* Board-changes indicator + Input bar, stick to bottom */}
                <div className="bg-surface">
                    {pendingBoardChanges > 0 && !isStreaming && (
                        <div
                            className="bg-accent-light px-6 py-1.5 text-center text-xs text-accent"
                            data-testid="board-changes-indicator"
                        >
                            {t('board_changes.count', {
                                count: pendingBoardChanges,
                            })}
                        </div>
                    )}
                    <div className="mx-auto w-full max-w-[720px] px-6 py-4">
                        <div className="relative">
                            <Textarea
                                ref={inputRef}
                                rows={1}
                                value={input}
                                onChange={(e) => setInput(e.target.value)}
                                onKeyDown={handleKeyDown}
                                placeholder={t('input.placeholder')}
                                aria-label={t('input.placeholder')}
                                disabled={isStreaming}
                                className="block max-h-40 min-h-12 overflow-y-auto py-3 pr-14 pl-4 text-sm leading-[1.4]"
                            />
                            <Button
                                type="button"
                                variant="accent"
                                size="icon"
                                onClick={handleSend}
                                disabled={!input.trim() || isStreaming}
                                aria-label={t('input.send')}
                                className="absolute right-1.5 bottom-1.5"
                            >
                                <ArrowUp className="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        );
    },
);

export default ChatSurface;

function IntakeOpener({ hello, body }: { hello: string; body: string }) {
    return (
        <div className="flex gap-3">
            <CoachAvatar />
            <div className="flex-1">
                <p className="text-sm leading-[1.55] font-medium text-ink">
                    {hello}
                </p>
                <p className="mt-1 text-sm leading-[1.55] text-ink-muted">
                    {body}
                </p>
            </div>
        </div>
    );
}

function UserBubble({ content }: { content: string }) {
    return (
        <div className="flex justify-end">
            <Card className="max-w-[480px] px-4 py-3 text-sm leading-[1.55] text-ink">
                <p className="whitespace-pre-wrap">{content}</p>
            </Card>
        </div>
    );
}

type AssistantRowProps = {
    content: string;
    streaming?: boolean;
    thinkingLabel: string;
    /** Humanized label for the currently-running tool, or null if none. */
    toolStatusLabel?: string | null;
    isActiveProposalRow: boolean;
    proposalStates: Record<string, ProposalState>;
    onApprove: (id: string) => void;
    onCancel: (id: string) => void;
    onUndo: (id: string) => void;
};

function AssistantRow({
    content,
    streaming,
    thinkingLabel,
    toolStatusLabel,
    isActiveProposalRow,
    proposalStates,
    onApprove,
    onCancel,
    onUndo,
}: AssistantRowProps) {
    const parsed = useMemo(() => parseAssistantMessage(content), [content]);

    const hasAnyRendered =
        parsed.preamble.trim().length > 0 ||
        parsed.postscript.trim().length > 0 ||
        parsed.proposal !== null;

    return (
        <div className="flex gap-3">
            <CoachAvatar />
            <div className="min-w-0 flex-1">
                {hasAnyRendered ? (
                    <div className="flex flex-col gap-3">
                        {parsed.preamble && (
                            <div className="flex items-start gap-1">
                                <AssistantMessage content={parsed.preamble} />
                                {streaming &&
                                    !parsed.proposal &&
                                    !parsed.postscript && <StreamingDot />}
                            </div>
                        )}

                        {parsed.proposal && (
                            <BatchProposalCard
                                proposalId={parsed.proposal.proposalId}
                                writes={parsed.proposal.writes}
                                summary={parsed.proposal.summary}
                                state={
                                    proposalStates[
                                        parsed.proposal.proposalId
                                    ] ?? 'pending'
                                }
                                onApprove={onApprove}
                                onCancel={onCancel}
                                onUndo={onUndo}
                                dimmed={!isActiveProposalRow}
                            />
                        )}

                        {parsed.postscript && (
                            <div className="flex items-start gap-1">
                                <AssistantMessage content={parsed.postscript} />
                                {streaming && <StreamingDot />}
                            </div>
                        )}

                        {streaming && toolStatusLabel && (
                            <div className="flex items-center gap-1.5 text-xs text-ink-muted">
                                <Loader
                                    size={12}
                                    className="animate-spin text-accent"
                                />
                                <span>{toolStatusLabel}</span>
                            </div>
                        )}
                    </div>
                ) : streaming ? (
                    <div className="flex items-center gap-1.5">
                        <Loader
                            size={14}
                            className="animate-spin text-accent"
                        />
                        <span className="text-xs text-ink-muted">
                            {toolStatusLabel ?? thinkingLabel}
                        </span>
                    </div>
                ) : null}
            </div>
        </div>
    );
}

function CoachAvatar() {
    return (
        <div className="flex size-7 shrink-0 items-center justify-center rounded-full bg-accent-light text-accent">
            <Sparkles className="size-3.5" />
        </div>
    );
}

function StreamingDot() {
    return (
        <span
            aria-hidden="true"
            className="ml-1 inline-block size-1.5 shrink-0 translate-y-[7px] animate-pulse rounded-full bg-accent"
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
        <Alert variant="destructive">
            <div className="flex items-start gap-3">
                <AlertDescription className="flex-1 text-[13px]">
                    {message}
                </AlertDescription>
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    onClick={onRetry}
                >
                    {retryLabel}
                </Button>
            </div>
        </Alert>
    );
}
