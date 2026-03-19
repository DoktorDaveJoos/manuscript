import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Trans, useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import Dialog from '@/components/ui/Dialog';
import type { Chapter } from '@/types/models';
import { destroy } from '@/actions/App/Http/Controllers/ChapterController';

export default function DeleteChapterDialog({
    bookId,
    chapter,
    onClose,
}: {
    bookId: number;
    chapter: Chapter;
    onClose: () => void;
}) {
    const { t } = useTranslation('editor');
    const [processing, setProcessing] = useState(false);

    function handleDelete() {
        setProcessing(true);
        router.delete(destroy.url({ book: bookId, chapter: chapter.id }), {
            onSuccess: () => onClose(),
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <Dialog
            onClose={onClose}
            width={440}
            backdrop="light"
            className="gap-7"
        >
            <div className="flex flex-col gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-[10px] bg-delete-bg">
                    <svg
                        className="h-5 w-5 text-delete"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        strokeWidth={2}
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
                        />
                    </svg>
                </div>
                <h2 className="font-serif text-[32px] leading-10 tracking-[-0.01em] text-ink">
                    {t('deleteChapter.title')}
                </h2>
                <p className="text-sm leading-[22px] text-ink-muted">
                    <Trans
                        i18nKey="deleteChapter.description"
                        ns="editor"
                        values={{ title: chapter.title }}
                    >
                        This will move{' '}
                        <span className="font-medium text-ink">
                            {'{{title}}'}
                        </span>{' '}
                        and its scenes to the trash. You can restore it later
                        from the sidebar.
                    </Trans>
                </p>
            </div>

            <div className="flex items-center justify-end gap-3">
                <Button
                    variant="ghost"
                    size="lg"
                    type="button"
                    onClick={onClose}
                >
                    {t('deleteChapter.cancel')}
                </Button>
                <Button
                    variant="danger"
                    size="lg"
                    type="button"
                    disabled={processing}
                    onClick={handleDelete}
                >
                    {t('deleteChapter.confirm')}
                </Button>
            </div>
        </Dialog>
    );
}
