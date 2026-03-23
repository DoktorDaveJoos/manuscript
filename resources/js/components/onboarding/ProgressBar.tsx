import { useTranslation } from 'react-i18next';

type StatusCounts = {
    final: number;
    revised: number;
    draft: number;
};

export default function ProgressBar({ counts }: { counts: StatusCounts }) {
    const { t } = useTranslation('onboarding');
    const total = counts.final + counts.revised + counts.draft;
    if (total === 0) return null;

    const segments: {
        count: number;
        label: 'final' | 'revised' | 'draft';
        color: string;
    }[] = [];
    if (counts.final > 0)
        segments.push({
            count: counts.final,
            label: 'final',
            color: 'bg-status-final',
        });
    if (counts.revised > 0)
        segments.push({
            count: counts.revised,
            label: 'revised',
            color: 'bg-status-revised',
        });
    if (counts.draft > 0)
        segments.push({
            count: counts.draft,
            label: 'draft',
            color: 'bg-status-draft',
        });

    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-center gap-1.5">
                {segments.map((s) => (
                    <div
                        key={s.label}
                        className={`h-2 rounded ${s.color}`}
                        style={{
                            flexGrow: s.count,
                            flexShrink: 1,
                            flexBasis: '0%',
                        }}
                    />
                ))}
            </div>
            <div className="flex items-center gap-4">
                {segments.map((s) => (
                    <span
                        key={s.label}
                        className="flex items-center gap-1.5 text-[11px] leading-[14px] text-ink-faint"
                    >
                        <span className={`size-2.5 rounded-[2px] ${s.color}`} />
                        {s.count} {t(`progressBar.${s.label}` as const)}
                    </span>
                ))}
            </div>
        </div>
    );
}
