import Kbd from '@/components/ui/Kbd';
import type { Chapter } from '@/types/models';
import { History } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import StatusBadge from './StatusBadge';

type SaveStatus = 'saved' | 'saving' | 'unsaved' | 'error';

export default function EditorBar({
    chapter,
    chapterTitle,
    storylineName,
    wordCount,
    versionCount,
    saveStatus,
    onVersionClick,
}: {
    chapter: Chapter;
    chapterTitle: string;
    storylineName: string;
    wordCount: number;
    versionCount: number;
    saveStatus: SaveStatus;
    onVersionClick: () => void;
}) {
    const { t, i18n } = useTranslation('editor');

    return (
        <div className="flex h-12 shrink-0 items-center justify-between border-b border-border px-6">
            <div className="flex items-center gap-3">
                <span className="flex items-center gap-1.5 text-sm">
                    <span className="text-ink-faint">{storylineName}</span>
                    <span className="text-ink-faint">/</span>
                    <span className="text-ink">{chapterTitle}</span>
                </span>
                <StatusBadge status={chapter.status} />
            </div>

            <div className="flex items-center gap-4">
                <span
                    className={`text-xs ${saveStatus === 'error' ? 'text-red-600' : saveStatus === 'saving' ? 'text-ink-faint' : 'text-ink-muted'}`}
                >
                    {t(`saveStatus.${saveStatus}`)}
                </span>
                <span className="text-xs text-ink-faint">{t('wordCount', { count: wordCount, formatted: wordCount.toLocaleString(i18n.language) })}</span>
                <button
                    type="button"
                    onClick={onVersionClick}
                    className="flex items-center gap-1 rounded-md border border-border px-2 py-1 text-xs text-ink-muted transition-colors hover:bg-neutral-bg hover:text-ink"
                >
                    <History size={12} />
                    v{versionCount}
                </button>
                <Kbd keys="⇧Tab" />
            </div>
        </div>
    );
}

export type { SaveStatus };
