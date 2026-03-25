import { BookOpen, RefreshCw, Sparkles, Wand2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Card } from '@/components/ui/Card';
import SectionLabel from '@/components/ui/SectionLabel';
import { useAiPreparation } from '@/hooks/useAiPreparation';
import type { AiPreparationStatus } from '@/types/models';

function formatTimeAgo(dateString: string): string {
    const now = new Date();
    const date = new Date(dateString);
    const seconds = Math.floor((now.getTime() - date.getTime()) / 1000);
    if (seconds < 60) return 'just now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    return `${days}d ago`;
}

function CommandCard({
    icon,
    title,
    description,
    footer,
}: {
    icon: ReactNode;
    title: string;
    description: string;
    footer: ReactNode;
}) {
    return (
        <Card className="flex flex-1 flex-col justify-between p-5">
            <div className="flex flex-col gap-3">
                <div className="flex size-10 items-center justify-center rounded-xl bg-neutral-bg">
                    {icon}
                </div>
                <div className="flex flex-col gap-1">
                    <span className="text-[14px] font-semibold text-ink">
                        {title}
                    </span>
                    <span className="text-[12px] leading-[1.5] text-ink-muted">
                        {description}
                    </span>
                </div>
            </div>
            <div className="mt-4 border-t border-border-subtle pt-3">
                {footer}
            </div>
        </Card>
    );
}

export default function CommandCenter({
    bookId,
    initialStatus,
}: {
    bookId: number;
    initialStatus: AiPreparationStatus | null;
}) {
    const { t } = useTranslation('ai-dashboard');
    const { status, starting, handleStart } = useAiPreparation(
        bookId,
        initialStatus,
    );

    const lastPrepTime = status?.updated_at
        ? formatTimeAgo(status.updated_at)
        : null;

    return (
        <div className="flex flex-col gap-3">
            <SectionLabel>{t('commandCenter.label')}</SectionLabel>
            <div className="flex gap-4">
                <CommandCard
                    icon={
                        <Sparkles
                            size={20}
                            strokeWidth={1.5}
                            className="text-ink-muted"
                        />
                    }
                    title={t('commandCenter.preparation.title')}
                    description={t('commandCenter.preparation.description')}
                    footer={
                        <div className="flex items-center justify-between">
                            {lastPrepTime && (
                                <span className="text-[11px] text-ink-faint">
                                    {t('commandCenter.preparation.lastRun', {
                                        time: lastPrepTime,
                                    })}
                                </span>
                            )}
                            <button
                                type="button"
                                onClick={handleStart}
                                disabled={starting}
                                className="ml-auto inline-flex items-center gap-1.5 text-[12px] font-medium text-accent transition-colors hover:text-accent/80 disabled:opacity-50"
                            >
                                <RefreshCw size={12} />
                                {t('commandCenter.preparation.reanalyze')}
                            </button>
                        </div>
                    }
                />

                <CommandCard
                    icon={
                        <Wand2
                            size={20}
                            strokeWidth={1.5}
                            className="text-ink-muted"
                        />
                    }
                    title={t('commandCenter.beautify.title')}
                    description={t('commandCenter.beautify.description')}
                    footer={
                        <span className="text-[12px] font-medium text-ink-faint">
                            {t('commandCenter.beautify.open')}
                        </span>
                    }
                />

                <CommandCard
                    icon={
                        <BookOpen
                            size={20}
                            strokeWidth={1.5}
                            className="text-ink-muted"
                        />
                    }
                    title={t('commandCenter.prosePass.title')}
                    description={t('commandCenter.prosePass.description')}
                    footer={
                        <span className="text-[12px] font-medium text-ink-faint">
                            {t('commandCenter.prosePass.open')}
                        </span>
                    }
                />
            </div>
        </div>
    );
}
