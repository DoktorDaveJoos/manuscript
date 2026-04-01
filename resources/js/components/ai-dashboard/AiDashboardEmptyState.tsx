import { Link, usePage } from '@inertiajs/react';
import { Loader2, Lock } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { index as settingsIndex } from '@/actions/App/Http/Controllers/SettingsController';
import { Alert, AlertDescription } from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import { Card, CardContent } from '@/components/ui/Card';
import { TOTAL_PHASES, useAiPreparation } from '@/hooks/useAiPreparation';
import { useLicense } from '@/hooks/useLicense';
import type { AiPreparationStatus } from '@/types/models';

const FEATURE_CARDS = [
    {
        titleKey: 'emptyState.features.clarity.title',
        descKey: 'emptyState.features.clarity.description',
    },
    {
        titleKey: 'emptyState.features.stuck.title',
        descKey: 'emptyState.features.stuck.description',
    },
    {
        titleKey: 'emptyState.features.polish.title',
        descKey: 'emptyState.features.polish.description',
    },
    {
        titleKey: 'emptyState.features.opinion.title',
        descKey: 'emptyState.features.opinion.description',
    },
] as const;

export default function AiDashboardEmptyState({
    bookId,
    initialStatus,
}: {
    bookId: number;
    initialStatus: AiPreparationStatus | null;
}) {
    const { t } = useTranslation('ai-dashboard');
    const pageUrl = usePage().url;
    const { isFree } = useLicense();
    const { starting, error, status, isRunning, handleStart } =
        useAiPreparation(bookId, initialStatus);

    return (
        <div className="flex flex-1 flex-col items-center gap-12 py-16">
            {/* Hero */}
            <div className="flex max-w-md flex-col items-center gap-4 text-center">
                <h2 className="font-serif text-[32px] leading-[1.2] font-semibold tracking-[-0.01em] text-ink">
                    {t('emptyState.heading')}
                </h2>
                <p className="text-[14px] leading-[1.6] text-ink-muted">
                    {t('emptyState.description')}
                </p>
                {isFree ? (
                    <Button
                        variant="primary"
                        disabled
                        className="mt-2 text-[13px]"
                    >
                        <Lock size={14} />
                        {t('emptyState.cta')}
                    </Button>
                ) : isRunning ? (
                    <div className="mt-2 flex flex-col items-center gap-3">
                        <Loader2
                            size={20}
                            strokeWidth={1.5}
                            className="animate-spin text-accent"
                        />
                        <p className="text-[13px] font-medium text-ink">
                            {t(
                                `commandCenter.preparation.phase.${status?.current_phase ?? 'default'}`,
                            )}
                        </p>
                        <p className="text-[12px] text-ink-faint">
                            {t('commandCenter.preparation.step', {
                                current:
                                    (status?.completed_phases?.length ?? 0) + 1,
                                total: TOTAL_PHASES,
                            })}
                            {status?.current_phase_total
                                ? ` · ${status.current_phase_progress}/${status.current_phase_total}`
                                : null}
                        </p>
                    </div>
                ) : (
                    <Button
                        variant="primary"
                        onClick={handleStart}
                        disabled={starting}
                        className="mt-2 text-[13px]"
                    >
                        {t('emptyState.cta')}
                    </Button>
                )}
                {error && (
                    <Alert variant="destructive" className="max-w-md">
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}
                <div className="flex items-center gap-4 text-[13px]">
                    <a
                        href="https://getmanuscript.app/ai"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-accent transition-colors hover:text-accent/80"
                    >
                        {t('emptyState.learnMore')}
                    </a>
                    <Link
                        href={settingsIndex.url({
                            query: {
                                from: pageUrl,
                                section: 'ai-features',
                            },
                        })}
                        className="text-ink-muted transition-colors hover:text-ink"
                    >
                        {t('emptyState.configureProvider')}
                    </Link>
                </div>
            </div>

            {/* Feature cards */}
            <div className="w-full max-w-4xl">
                <span className="mb-4 block text-[11px] font-semibold tracking-[0.08em] text-ink-muted uppercase">
                    {t('emptyState.features.title')}
                </span>
                <div className="grid grid-cols-4 gap-6">
                    {FEATURE_CARDS.map((card) => (
                        <Card key={card.titleKey}>
                            <CardContent className="p-8">
                                <h3 className="text-[16px] font-medium text-ink">
                                    {t(card.titleKey)}
                                </h3>
                                <p className="mt-2 text-[13px] leading-[1.6] text-ink-muted italic">
                                    {t(card.descKey)}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </div>
    );
}
