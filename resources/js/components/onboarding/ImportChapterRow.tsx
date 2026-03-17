import { useTranslation } from 'react-i18next';

type ChapterItem = {
    title: string;
    wordCount: number | null;
    done: boolean;
};

export default function ImportChapterRow({
    chapter,
}: {
    chapter: ChapterItem;
}) {
    const { t, i18n } = useTranslation('onboarding');
    return (
        <div className="flex items-center gap-3 border-b border-border-light py-3.5">
            {chapter.done ? (
                <div className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-ink">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                        <path
                            d="M2.5 6l2.5 2.5 4.5-5"
                            stroke="#FCFAF7"
                            strokeWidth="1.5"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        />
                    </svg>
                </div>
            ) : (
                <div className="h-5 w-5 shrink-0 rounded-full border-[1.5px] border-ink-faint" />
            )}

            <span
                className={`text-sm leading-[18px] ${chapter.done ? 'text-ink' : 'text-ink-muted'}`}
            >
                {chapter.title}
            </span>

            <span className="ml-auto text-xs leading-4 text-ink-faint">
                {chapter.wordCount !== null
                    ? t('importChapterRow.words', {
                          count: chapter.wordCount,
                          formatted: chapter.wordCount.toLocaleString(
                              i18n.language,
                          ),
                      })
                    : '...'}
            </span>
        </div>
    );
}

export type { ChapterItem };
