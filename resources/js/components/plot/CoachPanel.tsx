import { Link } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { forwardRef, useImperativeHandle, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import ChatSurface from '@/components/plot/ChatSurface';
import type { ChatSurfaceHandle } from '@/components/plot/ChatSurface';
import Button from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';

type CoachPanelProps = {
    aiConfigured: boolean;
    bookId: number;
    activeSessionId: number | null;
    onSessionCreated?: (sessionId: number) => void;
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
        { aiConfigured, bookId, activeSessionId, onSessionCreated },
        ref,
    ) {
        const { t } = useTranslation('plot-coach');

        const chatRef = useRef<ChatSurfaceHandle>(null);

        useImperativeHandle(
            ref,
            () => ({
                sendSystemSignal: (message: string) => {
                    chatRef.current?.sendSystemSignal(message);
                },
                fillInput: (text: string) => {
                    chatRef.current?.fillInput(text);
                },
            }),
            [],
        );

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
            <ChatSurface
                ref={chatRef}
                bookId={bookId}
                sessionId={activeSessionId}
                onSessionCreated={onSessionCreated}
            />
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
                <Button variant="primary" size="sm" asChild>
                    <Link href={href}>{cta}</Link>
                </Button>
            </div>
        </Card>
    );
}
