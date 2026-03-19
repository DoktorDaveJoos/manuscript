import { useTranslation } from 'react-i18next';
import { STATUS_COLORS, TYPE_STYLES } from '@/lib/plot-constants';
import type { PlotPoint } from '@/types/models';

type Props = {
    plotPoint: PlotPoint;
    chapterWordCount?: number;
    onClick: () => void;
};

function formatWordCount(count: number): string {
    if (count >= 1000) {
        return `${(count / 1000).toFixed(1).replace(/\.0$/, '')}k`;
    }
    return String(count);
}

const MAX_VISIBLE_CHARACTERS = 3;

export default function PlotPointCard({
    plotPoint,
    chapterWordCount,
    onClick,
}: Props) {
    const { t } = useTranslation('plot');
    const characters = plotPoint.characters ?? [];
    const visibleChars = characters.slice(0, MAX_VISIBLE_CHARACTERS);
    const overflowCount = characters.length - MAX_VISIBLE_CHARACTERS;

    return (
        <button
            onClick={(e) => {
                e.stopPropagation();
                onClick();
            }}
            className="w-full rounded border border-border bg-surface-card px-2.5 py-2 text-left shadow-[0_1px_2px_rgba(0,0,0,0.04)] transition-shadow hover:shadow-[0_2px_4px_rgba(0,0,0,0.08)]"
        >
            <div className="flex items-start gap-1.5">
                <div
                    className="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full"
                    style={{
                        backgroundColor:
                            STATUS_COLORS[plotPoint.status] ?? '#B0A99F',
                    }}
                />
                <span className="text-xs leading-tight font-medium text-ink">
                    {plotPoint.title}
                </span>
            </div>

            {plotPoint.description && (
                <p className="mt-1 line-clamp-2 text-[11px] leading-[15px] text-ink-muted">
                    {plotPoint.description}
                </p>
            )}

            <div className="mt-1.5 flex items-center justify-between gap-1">
                <div className="flex items-center gap-1.5">
                    <span
                        className={`inline-block rounded px-1.5 py-0.5 text-[10px] font-medium ${TYPE_STYLES[plotPoint.type] ?? ''}`}
                    >
                        {t(`type.${plotPoint.type}`)}
                    </span>
                    {chapterWordCount != null && chapterWordCount > 0 && (
                        <span className="text-[10px] text-ink-faint">
                            {t('card.wordCount', {
                                count: formatWordCount(chapterWordCount),
                            })}
                        </span>
                    )}
                </div>

                {characters.length > 0 && (
                    <div className="flex items-center gap-0.5">
                        {visibleChars.map((char) => (
                            <span
                                key={char.id}
                                title={char.name}
                                className="flex h-4 w-4 items-center justify-center rounded-full bg-neutral-bg text-[9px] font-semibold text-ink-soft uppercase"
                            >
                                {char.name.charAt(0)}
                            </span>
                        ))}
                        {overflowCount > 0 && (
                            <span className="text-[9px] text-ink-faint">
                                {t('card.moreCharacters', {
                                    count: overflowCount,
                                })}
                            </span>
                        )}
                    </div>
                )}
            </div>
        </button>
    );
}
