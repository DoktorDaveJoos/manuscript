import { ArrowLeft, Download } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    sessionExport,
    sessionIndex,
    sessionShow,
} from '@/actions/App/Http/Controllers/PlotCoachController';
import Button from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import Drawer from '@/components/ui/Drawer';
import PanelHeader from '@/components/ui/PanelHeader';
import { md } from '@/lib/markdown';
import { jsonFetchHeaders } from '@/lib/utils';

type ArchiveDrawerProps = {
    bookId: number;
    open: boolean;
    onClose: () => void;
};

type ArchivedSession = {
    id: number;
    status: string;
    stage: string;
    archived_at: string | null;
    created_at: string;
    updated_at: string;
    user_turn_count: number | null;
    input_tokens: number | null;
    output_tokens: number | null;
    cost_cents: number | null;
};

type TranscriptMessage = {
    role: 'user' | 'assistant';
    content: string;
};

type SessionShowResponse = {
    id: number;
    messages: TranscriptMessage[];
};

export default function ArchiveDrawer({
    bookId,
    open,
    onClose,
}: ArchiveDrawerProps) {
    const { t, i18n } = useTranslation('plot-coach');
    const [sessions, setSessions] = useState<ArchivedSession[]>([]);
    const [loadingList, setLoadingList] = useState(false);
    const [activeSessionId, setActiveSessionId] = useState<number | null>(null);
    const [transcript, setTranscript] = useState<TranscriptMessage[]>([]);
    const [loadingTranscript, setLoadingTranscript] = useState(false);

    useEffect(() => {
        if (!open) return;

        const controller = new AbortController();
        setLoadingList(true);
        setActiveSessionId(null);
        setTranscript([]);

        fetch(sessionIndex.url({ book: bookId }), {
            headers: jsonFetchHeaders(),
            signal: controller.signal,
        })
            .then((res) => (res.ok ? res.json() : []))
            .then((rows: ArchivedSession[]) => {
                const archived = Array.isArray(rows)
                    ? rows.filter((r) => r.status === 'archived')
                    : [];
                setSessions(archived);
            })
            .catch((err) => {
                if (err?.name === 'AbortError') return;
                setSessions([]);
            })
            .finally(() => setLoadingList(false));

        return () => controller.abort();
    }, [bookId, open]);

    const loadTranscript = useCallback(
        (sessionId: number) => {
            setActiveSessionId(sessionId);
            setLoadingTranscript(true);
            setTranscript([]);

            fetch(sessionShow.url({ book: bookId, session: sessionId }), {
                headers: jsonFetchHeaders(),
            })
                .then((res) => (res.ok ? res.json() : null))
                .then((data: SessionShowResponse | null) => {
                    if (!data) {
                        setTranscript([]);
                        return;
                    }
                    setTranscript(data.messages ?? []);
                })
                .catch(() => setTranscript([]))
                .finally(() => setLoadingTranscript(false));
        },
        [bookId],
    );

    const dateFormatter = useMemo(
        () =>
            new Intl.DateTimeFormat(i18n.language, {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
            }),
        [i18n.language],
    );

    const formatDate = useCallback(
        (iso: string | null) => {
            if (!iso) return '—';
            const parsed = new Date(iso);
            if (Number.isNaN(parsed.getTime())) return '—';
            return dateFormatter.format(parsed);
        },
        [dateFormatter],
    );

    if (!open) return null;

    if (activeSessionId !== null) {
        return (
            <Drawer onClose={onClose} className="w-[480px]">
                <TranscriptView
                    bookId={bookId}
                    sessionId={activeSessionId}
                    loading={loadingTranscript}
                    messages={transcript}
                    onBack={() => {
                        setActiveSessionId(null);
                        setTranscript([]);
                    }}
                    t={t}
                />
            </Drawer>
        );
    }

    return (
        <Drawer onClose={onClose}>
            <PanelHeader title={t('archive.title')} onClose={onClose} />

            <div className="flex-1 overflow-y-auto px-4 py-3">
                {loadingList && (
                    <p className="text-[13px] text-ink-muted">
                        {t('archive.loading')}
                    </p>
                )}

                {!loadingList && sessions.length === 0 && (
                    <p className="text-[13px] text-ink-muted">
                        {t('archive.empty')}
                    </p>
                )}

                <ul className="flex flex-col gap-2">
                    {sessions.map((session) => (
                        <li key={session.id}>
                            <Card className="bg-surface px-3 py-3">
                                <div className="flex items-baseline justify-between">
                                    <span className="text-[13px] font-medium text-ink">
                                        {t('archive.archived_on', {
                                            date: formatDate(
                                                session.archived_at,
                                            ),
                                        })}
                                    </span>
                                    <span className="text-[11px] font-medium tracking-wide text-ink-muted uppercase">
                                        {t('archive.stage_label', {
                                            stage: session.stage,
                                        })}
                                    </span>
                                </div>
                                <div className="mt-1 text-xs text-ink-muted">
                                    {t('archive.turn_count', {
                                        count: session.user_turn_count ?? 0,
                                    })}
                                </div>
                                <div className="mt-3 flex gap-2">
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() =>
                                            loadTranscript(session.id)
                                        }
                                    >
                                        {t('archive.view')}
                                    </Button>
                                    <Button size="sm" variant="ghost" asChild>
                                        <a
                                            href={sessionExport.url({
                                                book: bookId,
                                                session: session.id,
                                            })}
                                        >
                                            <Download className="size-3.5" />
                                            {t('archive.export')}
                                        </a>
                                    </Button>
                                </div>
                            </Card>
                        </li>
                    ))}
                </ul>
            </div>
        </Drawer>
    );
}

function TranscriptView({
    bookId,
    sessionId,
    loading,
    messages,
    onBack,
    t,
}: {
    bookId: number;
    sessionId: number;
    loading: boolean;
    messages: TranscriptMessage[];
    onBack: () => void;
    t: (key: string, opts?: Record<string, unknown>) => string;
}) {
    return (
        <>
            <div className="flex h-11 shrink-0 items-center justify-between border-b border-border px-4">
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={onBack}
                    className="-ml-2"
                >
                    <ArrowLeft className="size-3.5" />
                    {t('archive.close_transcript')}
                </Button>
                <Button size="sm" variant="ghost" asChild>
                    <a
                        href={sessionExport.url({
                            book: bookId,
                            session: sessionId,
                        })}
                    >
                        <Download className="size-3.5" />
                        {t('archive.export')}
                    </a>
                </Button>
            </div>

            <div className="flex flex-1 flex-col gap-3 overflow-y-auto px-4 py-3">
                <Card className="bg-surface px-3 py-2 text-[11px] font-medium tracking-wide text-ink-muted uppercase">
                    {t('archive.read_only')}
                </Card>

                {loading && (
                    <p className="text-[13px] text-ink-muted">
                        {t('archive.loading')}
                    </p>
                )}

                {!loading &&
                    messages.map((message, i) => (
                        <TranscriptMessageView key={i} message={message} />
                    ))}
            </div>
        </>
    );
}

function TranscriptMessageView({ message }: { message: TranscriptMessage }) {
    if (message.role === 'user') {
        return (
            <Card className="bg-surface px-3 py-2 text-[13px] text-ink">
                <span className="mr-1 text-[11px] font-medium tracking-wide text-ink-muted uppercase">
                    You
                </span>
                <span className="whitespace-pre-wrap">{message.content}</span>
            </Card>
        );
    }

    return (
        <div
            className="ai-chat-markdown text-[13px] leading-[1.55] text-ink"
            dangerouslySetInnerHTML={{ __html: md.render(message.content) }}
        />
    );
}
