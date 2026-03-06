import { cn } from '@/lib/utils';
import type { Chapter } from '@/types/models';
import { ClockCounterClockwise, NotePencil } from '@phosphor-icons/react';
import StatusBadge from './StatusBadge';

type SaveStatus = 'saved' | 'saving' | 'unsaved' | 'error';

const saveStatusLabel: Record<SaveStatus, string> = {
    saved: 'Saved',
    saving: 'Saving...',
    unsaved: 'Unsaved',
    error: 'Save error',
};

export default function EditorBar({
    chapter,
    chapterTitle,
    storylineName,
    wordCount,
    versionCount,
    saveStatus,
    onVersionClick,
    onNotesToggle,
    isNotesOpen,
    hasNotes,
}: {
    chapter: Chapter;
    chapterTitle: string;
    storylineName: string;
    wordCount: number;
    versionCount: number;
    saveStatus: SaveStatus;
    onVersionClick: () => void;
    onNotesToggle: () => void;
    isNotesOpen: boolean;
    hasNotes: boolean;
}) {
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
                    {saveStatusLabel[saveStatus]}
                </span>
                <span className="text-xs text-ink-faint">{wordCount.toLocaleString('en-US')} words</span>
                <button
                    type="button"
                    onClick={onNotesToggle}
                    className={cn(
                        'flex items-center gap-1 rounded-md border border-border px-2 py-1 text-xs transition-colors hover:bg-neutral-bg hover:text-ink',
                        isNotesOpen ? 'bg-neutral-bg text-ink' : 'text-ink-muted',
                    )}
                >
                    <NotePencil size={12} />
                    Notes
                    {hasNotes && !isNotesOpen && (
                        <span className="h-1.5 w-1.5 rounded-full bg-ink-muted" />
                    )}
                </button>
                <button
                    type="button"
                    onClick={onVersionClick}
                    className="flex items-center gap-1 rounded-md border border-border px-2 py-1 text-xs text-ink-muted transition-colors hover:bg-neutral-bg hover:text-ink"
                >
                    <ClockCounterClockwise size={12} />
                    v{versionCount}
                </button>
                <kbd className="rounded border border-border bg-neutral-bg px-1.5 py-0.5 text-[10px] text-ink-muted">
                    ⇧Tab
                </kbd>
            </div>
        </div>
    );
}

export type { SaveStatus };
