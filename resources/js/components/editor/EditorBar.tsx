import { cn } from '@/lib/utils';
import type { Chapter } from '@/types/models';
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
                    <span className="text-ink">{chapter.title}</span>
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
                    <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
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
                    <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    v{versionCount}
                </button>
            </div>
        </div>
    );
}

export type { SaveStatus };
