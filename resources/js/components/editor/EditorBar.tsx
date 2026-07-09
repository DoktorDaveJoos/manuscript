import { Check, Copy } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import Kbd from '@/components/ui/Kbd';
import type { Chapter } from '@/types/models';
import StatusBadge from './StatusBadge';

type SaveStatus = 'saved' | 'saving' | 'unsaved' | 'error';

export default function EditorBar({
    chapter,
    chapterTitle,
    storylineName,
    wordCount,
    saveStatus,
    getChapterText,
    onVersionClick,
}: {
    chapter: Chapter;
    chapterTitle: string;
    storylineName: string;
    wordCount: number;
    saveStatus: SaveStatus;
    getChapterText: () => string;
    onVersionClick: () => void;
}) {
    const { t, i18n } = useTranslation('editor');
    const versionNumber = chapter.current_version?.version_number ?? 1;

    const [copied, setCopied] = useState(false);
    const copiedTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(
        () => () => {
            if (copiedTimeout.current) {
                clearTimeout(copiedTimeout.current);
            }
        },
        [],
    );

    const handleCopyChapter = async () => {
        try {
            await navigator.clipboard.writeText(getChapterText());
            setCopied(true);
            if (copiedTimeout.current) {
                clearTimeout(copiedTimeout.current);
            }
            copiedTimeout.current = setTimeout(() => setCopied(false), 2000);
        } catch {
            // Clipboard unavailable — nothing to do.
        }
    };

    return (
        <div className="@container flex h-[38px] shrink-0 items-center justify-between px-8">
            <div className="flex min-w-0 items-center gap-1.5 text-[12px]">
                <span className="shrink-0 text-ink-faint">{storylineName}</span>
                <span className="shrink-0 text-ink-faint">/</span>
                <span className="min-w-0 truncate font-medium text-ink-soft">
                    {chapterTitle}
                </span>
                {saveStatus !== 'saved' && (
                    <span
                        className={`shrink-0 text-[11px] ${saveStatus === 'error' ? 'text-red-500' : 'text-ink-faint'}`}
                    >
                        {t(`saveStatus.${saveStatus}`)}
                    </span>
                )}
            </div>

            <div className="flex shrink-0 items-center gap-3.5">
                <StatusBadge
                    status={chapter.status}
                    className="hidden @md:inline-flex"
                />
                <span className="hidden text-[12px] text-ink-faint @lg:inline">
                    {t('wordCount', {
                        count: wordCount,
                        formatted: wordCount.toLocaleString(i18n.language),
                    })}
                </span>
                <button
                    type="button"
                    onClick={onVersionClick}
                    className="text-[12px] text-ink-faint transition-colors hover:text-ink"
                >
                    v{versionNumber}
                </button>
                <button
                    type="button"
                    onClick={handleCopyChapter}
                    title={t(
                        copied ? 'copyChapter.copied' : 'copyChapter.copy',
                    )}
                    aria-label={t(
                        copied ? 'copyChapter.copied' : 'copyChapter.copy',
                    )}
                    className={`flex h-6 w-6 shrink-0 items-center justify-center rounded transition-colors hover:bg-neutral-bg ${
                        copied
                            ? 'text-ai-green'
                            : 'text-ink-faint hover:text-ink'
                    }`}
                >
                    {copied ? <Check size={14} /> : <Copy size={14} />}
                </button>
                <Kbd keys="⌘P" className="hidden @xl:inline-flex" />
            </div>
        </div>
    );
}

export type { SaveStatus };
