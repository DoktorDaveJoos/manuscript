import Kbd from '@/components/ui/Kbd';
import type { Chapter } from '@/types/models';
import { useTranslation } from 'react-i18next';
import StatusBadge from './StatusBadge';

type SaveStatus = 'saved' | 'saving' | 'unsaved' | 'error';

export default function EditorBar({
    chapter,
    chapterTitle,
    storylineName,
    wordCount,
    saveStatus,
    versionCount,
    onVersionClick,
}: {
    chapter: Chapter;
    chapterTitle: string;
    storylineName: string;
    wordCount: number;
    saveStatus: SaveStatus;
    versionCount: number;
    onVersionClick: () => void;
}) {
    const { t, i18n } = useTranslation('editor');

    return (
        <div className="@container flex h-[38px] shrink-0 items-center justify-between px-8">
            <div className="flex min-w-0 items-center gap-1.5 text-[12px]">
                <span className="shrink-0 text-ink-faint">{storylineName}</span>
                <span className="shrink-0 text-[#D5D5D5]">/</span>
                <span className="min-w-0 truncate font-medium text-ink-soft">{chapterTitle}</span>
                {saveStatus !== 'saved' && (
                    <span className={`shrink-0 text-[11px] ${saveStatus === 'error' ? 'text-red-500' : 'text-ink-faint'}`}>
                        {t(`saveStatus.${saveStatus}`)}
                    </span>
                )}
            </div>

            <div className="flex shrink-0 items-center gap-3.5">
                <StatusBadge status={chapter.status} className="hidden @md:inline-flex" />
                <span className="hidden @lg:inline text-[12px] text-ink-faint">{t('wordCount', { count: wordCount, formatted: wordCount.toLocaleString(i18n.language) })}</span>
                <button
                    type="button"
                    onClick={onVersionClick}
                    className="text-[12px] text-ink-faint transition-colors hover:text-ink"
                >
                    v{versionCount}
                </button>
                <Kbd keys="⌘P" className="hidden @xl:inline-flex" />
            </div>
        </div>
    );
}

export type { SaveStatus };
