import { useTranslation } from 'react-i18next';

export type NanowrimoData = {
    year: number;
    is_active: boolean;
    target: number;
    total_words: number;
    progress_percent: number;
    days_remaining: number;
    days_elapsed: number;
    daily_pace: number;
    on_track: boolean;
};

export default function NaNoWriMoCard({
    nanowrimo,
    locale,
}: {
    nanowrimo: NanowrimoData;
    locale: string;
}) {
    const { t } = useTranslation('dashboard');

    return (
        <div className="flex items-start justify-between rounded-xl bg-surface-warm p-8">
            <div className="flex flex-col gap-4">
                <div className="flex items-center gap-2">
                    <span className="size-2 rounded-full bg-accent" />
                    <span className="text-[11px] font-medium tracking-[0.08em] text-ink-muted uppercase">
                        {t('nanowrimo.tag', { year: nanowrimo.year })}
                    </span>
                </div>

                <h2 className="font-serif text-2xl font-normal tracking-[-0.01em] text-ink">
                    {t('nanowrimo.heading')}
                </h2>

                <p className="max-w-lg text-[13px] leading-[1.5] text-ink-soft">
                    {t('nanowrimo.description')}
                </p>

                <div className="max-w-lg">
                    <div className="h-2 overflow-hidden rounded bg-accent-light">
                        <div
                            className="h-full rounded bg-accent transition-all duration-700"
                            style={{
                                width: `${nanowrimo.progress_percent}%`,
                            }}
                        />
                    </div>

                    <div className="mt-2 flex items-center justify-between">
                        <span className="text-[12px] text-ink-soft">
                            {t('nanowrimo.progress', {
                                current:
                                    nanowrimo.total_words.toLocaleString(
                                        locale,
                                    ),
                                target: nanowrimo.target.toLocaleString(locale),
                            })}
                        </span>
                        <span className="text-[12px] font-medium text-ink-muted">
                            {t('nanowrimo.daysRemaining', {
                                count: nanowrimo.days_remaining,
                            })}
                            {' — '}
                            {nanowrimo.on_track
                                ? t('nanowrimo.onTrack')
                                : t('nanowrimo.behindPace')}
                        </span>
                    </div>
                </div>
            </div>

            <div className="flex flex-col items-center gap-1">
                <span className="font-serif text-[32px] leading-[1] font-normal text-ink">
                    {nanowrimo.progress_percent.toFixed(1)}%
                </span>
                <span className="text-[13px] text-ink-muted">
                    {t('nanowrimo.complete')}
                </span>
            </div>
        </div>
    );
}
