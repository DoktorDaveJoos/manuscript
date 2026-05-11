import { Head } from '@inertiajs/react';
import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { closeWindow as closeDiffWindow } from '@/actions/App/Http/Controllers/ChapterDiffController';
import DiffView from '@/components/editor/DiffView';
import { jsonFetchHeaders } from '@/lib/utils';
import type { ChapterVersion } from '@/types/models';

const APPLIED_CHANNEL = 'manuscript:diff-applied';

type Props = {
    book: { id: number; title: string };
    chapter: { id: number; title: string };
    currentVersion: ChapterVersion | null;
    pendingVersion: ChapterVersion;
};

function firstLine(text: string): string {
    return text.split('\n')[0];
}

export default function ChapterDiffPage({
    book,
    chapter,
    currentVersion,
    pendingVersion,
}: Props) {
    const { t } = useTranslation('editor');

    const closeWindow = useCallback(() => {
        fetch(closeDiffWindow.url({ chapter: chapter.id }), {
            method: 'POST',
            headers: jsonFetchHeaders(),
            keepalive: true,
        }).catch(() => {});
    }, [chapter.id]);

    const handleApplied = useCallback(() => {
        try {
            const channel = new BroadcastChannel(APPLIED_CHANNEL);
            channel.postMessage({ bookId: book.id, chapterId: chapter.id });
            channel.close();
        } catch {
            /* */
        }
        closeWindow();
    }, [book.id, chapter.id, closeWindow]);

    const headTitle = t('continueWriting.diffWindow.title', {
        defaultValue: 'Review changes — {{chapter}}',
        chapter: firstLine(chapter.title),
    });

    if (!currentVersion) {
        return (
            <>
                <Head title={headTitle} />
                <div className="flex h-screen flex-col items-center justify-center gap-3 bg-surface px-12 text-center text-sm text-ink-muted">
                    <p>
                        {t('continueWriting.diffWindow.noPrevious', {
                            defaultValue:
                                'This is the first version of the chapter — nothing to compare against.',
                        })}
                    </p>
                    <button
                        type="button"
                        onClick={closeWindow}
                        className="text-xs font-medium text-accent transition-colors hover:text-accent/80"
                    >
                        {t('diff.refine.close', { defaultValue: 'Close' })}
                    </button>
                </div>
            </>
        );
    }

    // Auto-applied revisions land with status='accepted'. The user can still
    // deselect changes to revert paragraphs to the previous version — applied
    // as a new version on top of the AI revision via the 'review' mode.
    const mode = pendingVersion.status === 'accepted' ? 'review' : 'refine';

    return (
        <>
            <Head title={headTitle} />
            <div className="flex h-screen flex-col bg-surface">
                <DiffView
                    bookId={book.id}
                    chapterId={chapter.id}
                    chapterTitle={firstLine(chapter.title)}
                    currentVersion={currentVersion}
                    pendingVersion={pendingVersion}
                    mode={mode}
                    onApplied={handleApplied}
                    onClose={closeWindow}
                />
            </div>
        </>
    );
}
