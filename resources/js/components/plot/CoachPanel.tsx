import { Link } from '@inertiajs/react';
import { Archive, CircleStop, Lock, Sparkles } from 'lucide-react';
import { forwardRef, useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { sessionArchive } from '@/actions/App/Http/Controllers/PlotCoachController';
import ArchiveDrawer from '@/components/plot/ArchiveDrawer';
import ChatSurface from '@/components/plot/ChatSurface';
import type { ChatSurfaceHandle } from '@/components/plot/ChatSurface';
import Button from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { useAiFeatures } from '@/hooks/useAiFeatures';
import { jsonFetchHeaders } from '@/lib/utils';

type CoachPanelProps = {
    aiConfigured: boolean;
    bookId: number;
    activeSessionId: number | null;
    onSessionCreated?: (sessionId: number) => void;
    onSessionEnded?: () => void;
};

export type CoachPanelHandle = ChatSurfaceHandle;

/**
 * The Coach panel shell. Renders one of four states:
 *
 *   1. No Pro licence            → upgrade CTA
 *   2. Pro + no AI               → configure-AI CTA
 *   3. Pro + AI + no session     → ChatSurface with intake opener (first
 *                                   message creates the session server-side)
 *   4. Pro + AI + active session → ChatSurface with hydrated history
 */
const CoachPanel = forwardRef<CoachPanelHandle, CoachPanelProps>(
    function CoachPanel(
        {
            aiConfigured,
            bookId,
            activeSessionId,
            onSessionCreated,
            onSessionEnded,
        },
        ref,
    ) {
        const { t } = useTranslation('plot-coach');
        const { licensed } = useAiFeatures();
        const [archiveOpen, setArchiveOpen] = useState(false);
        const [archiving, setArchiving] = useState(false);

        const handleEndSession = useCallback(async () => {
            if (activeSessionId === null || archiving) return;
            if (!window.confirm(t('archive.end_session_confirm'))) return;

            setArchiving(true);
            try {
                const res = await fetch(
                    sessionArchive.url({
                        book: bookId,
                        session: activeSessionId,
                    }),
                    {
                        method: 'PATCH',
                        headers: jsonFetchHeaders(),
                    },
                );
                if (!res.ok) throw new Error('archive failed');
                onSessionEnded?.();
            } catch {
                window.alert(t('archive.end_session_error'));
            } finally {
                setArchiving(false);
            }
        }, [activeSessionId, archiving, bookId, onSessionEnded, t]);

        if (!licensed) {
            return (
                <GateShell>
                    <GateCard
                        icon={<Lock className="size-4" />}
                        title={t('gate.no_pro.title')}
                        body={t('gate.no_pro.body')}
                        cta={t('gate.no_pro.cta')}
                        href="/settings/license"
                    />
                </GateShell>
            );
        }

        if (!aiConfigured) {
            return (
                <GateShell>
                    <GateCard
                        icon={<Sparkles className="size-4" />}
                        title={t('gate.no_ai.title')}
                        body={t('gate.no_ai.body')}
                        cta={t('gate.no_ai.cta')}
                        href="/settings/ai"
                    />
                </GateShell>
            );
        }

        return (
            <div className="flex min-h-0 flex-1 flex-col">
                <div className="flex items-center justify-end gap-1 border-b border-border-light bg-surface px-4 py-2">
                    {activeSessionId !== null && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={handleEndSession}
                            disabled={archiving}
                        >
                            <CircleStop className="size-3.5" />
                            {t('archive.end_session')}
                        </Button>
                    )}
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => setArchiveOpen(true)}
                    >
                        <Archive className="size-3.5" />
                        {t('archive.open')}
                    </Button>
                </div>

                <ChatSurface
                    ref={ref}
                    bookId={bookId}
                    sessionId={activeSessionId}
                    onSessionCreated={onSessionCreated}
                />

                <ArchiveDrawer
                    bookId={bookId}
                    open={archiveOpen}
                    onClose={() => setArchiveOpen(false)}
                />
            </div>
        );
    },
);

export default CoachPanel;

function GateShell({ children }: { children: React.ReactNode }) {
    return (
        <div className="flex min-h-0 flex-1 items-center justify-center overflow-y-auto bg-surface px-6 py-12">
            <div className="mx-auto flex w-full max-w-[720px] flex-col items-center">
                {children}
            </div>
        </div>
    );
}

type GateCardProps = {
    icon: React.ReactNode;
    title: string;
    body: string;
    cta: string;
    href: string;
};

function GateCard({ icon, title, body, cta, href }: GateCardProps) {
    return (
        <Card className="w-full max-w-[520px] px-8 py-10 text-center">
            <div className="mx-auto mb-4 flex size-10 items-center justify-center rounded-full border border-border-light text-ink-muted">
                {icon}
            </div>
            <h2 className="text-base font-medium text-ink">{title}</h2>
            <p className="mx-auto mt-2 max-w-[380px] text-[13px] leading-[1.5] text-ink-muted">
                {body}
            </p>
            <div className="mt-6">
                <Button variant="accent" size="sm" asChild>
                    <Link href={href}>{cta}</Link>
                </Button>
            </div>
        </Card>
    );
}
