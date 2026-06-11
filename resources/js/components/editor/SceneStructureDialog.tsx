import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { apply as applySceneStructure } from '@/actions/App/Http/Controllers/SceneStructureController';
import { Alert, AlertDescription } from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import Dialog from '@/components/ui/Dialog';
import { useAiErrorToast } from '@/hooks/useAiErrorToast';
import { broadcastChapterDataChanged, jsonFetchHeaders } from '@/lib/utils';
import type { Book, Chapter } from '@/types/models';

export type SceneStructureProposal = {
    scenes: {
        title: string;
        start_paragraph: number;
        word_count: number;
        excerpt: string;
    }[];
    paragraph_count: number;
    current_scene_count: number;
    content_hash: string;
};

export default function SceneStructureDialog({
    book,
    chapter,
    proposal,
    onClose,
}: {
    book: Book;
    chapter: Chapter;
    proposal: SceneStructureProposal;
    onClose: () => void;
}) {
    const { t } = useTranslation('ai');
    const showAiErrorToast = useAiErrorToast();
    const [isApplying, setIsApplying] = useState(false);

    async function handleAccept() {
        setIsApplying(true);
        try {
            const response = await fetch(
                applySceneStructure.url({
                    book: book.id,
                    chapter: chapter.id,
                }),
                {
                    method: 'POST',
                    headers: {
                        ...jsonFetchHeaders(),
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        content_hash: proposal.content_hash,
                        scenes: proposal.scenes.map(
                            ({ title, start_paragraph }) => ({
                                title,
                                start_paragraph,
                            }),
                        ),
                    }),
                },
            );

            if (!response.ok) {
                if (response.status === 409) {
                    showAiErrorToast({ kind: 'stale_version' });
                    onClose();
                    return;
                }
                const body = await response.json().catch(() => null);
                throw new Error(
                    body?.message ?? t('structure.error.applyFailed'),
                );
            }

            broadcastChapterDataChanged(chapter.id);
            toast(t('structure.toast.applied'), {
                description: t('structure.toast.appliedDescription', {
                    count: proposal.scenes.length,
                }),
            });
            onClose();
        } catch (e) {
            toast.error(
                e instanceof Error
                    ? e.message
                    : t('structure.error.applyFailed'),
            );
        } finally {
            setIsApplying(false);
        }
    }

    return (
        <Dialog
            onClose={onClose}
            backdrop="dark"
            width={560}
            className="gap-5"
            title={t('structure.dialogTitle')}
        >
            <div className="flex flex-col gap-1" data-scene-structure-dialog>
                <h2 className="text-base font-medium text-ink">
                    {t('structure.dialogTitle')}
                    <span className="ml-2 text-xs font-normal text-ink-faint">
                        {t('structure.sceneCount', {
                            count: proposal.scenes.length,
                        })}
                    </span>
                </h2>
                <p className="text-xs text-ink-muted">
                    {t('structure.dialogSubtitle')}
                </p>
            </div>

            {proposal.current_scene_count > 1 && (
                <Alert variant="info">
                    <AlertDescription>
                        {t('structure.replaceWarning', {
                            count: proposal.current_scene_count,
                        })}
                    </AlertDescription>
                </Alert>
            )}

            <div className="max-h-[320px] divide-y divide-border-light overflow-y-auto rounded-lg border border-border-light">
                {proposal.scenes.map((scene, index) => (
                    <div
                        key={`${scene.start_paragraph}-${index}`}
                        className="flex gap-3 px-4 py-3"
                        data-scene-structure-row={index}
                    >
                        <span className="mt-0.5 text-xs font-medium text-ink-faint">
                            {index + 1}
                        </span>
                        <div className="flex min-w-0 flex-col gap-0.5">
                            <div className="flex items-baseline gap-2">
                                <span className="truncate text-sm font-medium text-ink">
                                    {scene.title}
                                </span>
                                <span className="shrink-0 text-xs text-ink-faint">
                                    {t('structure.words', {
                                        count: scene.word_count,
                                    })}
                                </span>
                            </div>
                            <p className="line-clamp-2 text-xs text-ink-muted italic">
                                {scene.excerpt}
                            </p>
                        </div>
                    </div>
                ))}
            </div>

            <div className="flex items-center justify-end gap-3">
                <Button variant="ghost" type="button" onClick={onClose}>
                    {t('structure.cancel')}
                </Button>
                <Button
                    variant="primary"
                    type="button"
                    onClick={handleAccept}
                    disabled={isApplying}
                >
                    {isApplying
                        ? t('structure.applying')
                        : t('structure.accept')}
                </Button>
            </div>
        </Dialog>
    );
}
