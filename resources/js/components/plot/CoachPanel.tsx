import { Link } from '@inertiajs/react';
import { ArrowUp, Lock, Sparkles } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';
import { useAiFeatures } from '@/hooks/useAiFeatures';

type CoachPanelProps = {
    aiConfigured: boolean;
};

/**
 * The Coach panel shell. Renders one of three gate states:
 *
 *   1. No Pro licence    → upgrade CTA
 *   2. Pro + no AI       → configure-AI CTA
 *   3. Pro + AI configured → intake empty state
 *
 * Streaming chat lands in Phase 2; this is the skeleton.
 */
export default function CoachPanel({ aiConfigured }: CoachPanelProps) {
    const { t } = useTranslation('plot-coach');
    const { licensed } = useAiFeatures();

    return (
        <div className="flex min-h-0 flex-1 items-center justify-center overflow-y-auto bg-surface px-6 py-12">
            <div className="mx-auto flex w-full max-w-[720px] flex-col items-center">
                {!licensed ? (
                    <GateCard
                        icon={<Lock className="h-4 w-4" />}
                        title={t('gate.no_pro.title')}
                        body={t('gate.no_pro.body')}
                        cta={t('gate.no_pro.cta')}
                        href="/settings/license"
                    />
                ) : !aiConfigured ? (
                    <GateCard
                        icon={<Sparkles className="h-4 w-4" />}
                        title={t('gate.no_ai.title')}
                        body={t('gate.no_ai.body')}
                        cta={t('gate.no_ai.cta')}
                        href="/settings/ai"
                    />
                ) : (
                    <IntakeEmptyState />
                )}
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
        <div className="w-full max-w-[520px] rounded-xl border border-border-light bg-surface-card px-8 py-10 text-center">
            <div className="mx-auto mb-4 flex h-10 w-10 items-center justify-center rounded-full border border-border-light text-ink-muted">
                {icon}
            </div>
            <h2 className="text-[15px] font-medium text-ink">{title}</h2>
            <p className="mx-auto mt-2 max-w-[380px] text-[13px] leading-[1.5] text-ink-muted">
                {body}
            </p>
            <div className="mt-6">
                <Button variant="accent" size="sm" asChild>
                    <Link href={href}>{cta}</Link>
                </Button>
            </div>
        </div>
    );
}

function IntakeEmptyState() {
    const { t } = useTranslation('plot-coach');

    return (
        <div className="flex w-full flex-col items-center">
            <div className="mb-8 flex flex-col items-center text-center">
                <div className="mb-5 flex h-10 w-10 items-center justify-center rounded-full border border-border-light bg-surface-card text-accent">
                    <Sparkles className="h-4 w-4" />
                </div>
                <h2 className="text-[18px] font-medium text-ink">
                    {t('empty_state.title')}
                </h2>
                <p className="mt-2 max-w-[420px] text-[13px] leading-[1.5] text-ink-muted">
                    {t('empty_state.body')}
                </p>
            </div>

            <div className="relative w-full max-w-[620px]">
                <Input
                    type="text"
                    disabled
                    placeholder={t('empty_state.placeholder')}
                    aria-label={t('empty_state.placeholder')}
                    className="h-12 rounded-full border-border-light bg-surface-card pr-14 pl-5 text-[13px]"
                />
                <button
                    type="button"
                    disabled
                    aria-label={t('empty_state.placeholder')}
                    className="absolute top-1/2 right-1.5 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-ink text-surface transition-colors hover:bg-ink/90 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <ArrowUp className="h-4 w-4" />
                </button>
            </div>
        </div>
    );
}
