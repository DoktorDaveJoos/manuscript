import { Link, usePage } from '@inertiajs/react';
import { FileSearch, ListTree, PenTool, Sparkles, Wand2 } from 'lucide-react';
import { useCallback, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import {
    revise,
    reviseScene,
    reviseWithEditorialFeedback,
} from '@/actions/App/Http/Controllers/AiController';
import { openWindow as openDiffWindow } from '@/actions/App/Http/Controllers/ChapterDiffController';
import { suggest as suggestSceneStructure } from '@/actions/App/Http/Controllers/SceneStructureController';
import { index as settingsIndex } from '@/actions/App/Http/Controllers/SettingsController';
import SceneStructureDialog from '@/components/editor/SceneStructureDialog';
import type { SceneStructureProposal } from '@/components/editor/SceneStructureDialog';
import Button from '@/components/ui/Button';
import PanelHeader from '@/components/ui/PanelHeader';
import SectionLabel from '@/components/ui/SectionLabel';
import { useAiErrorToast } from '@/hooks/useAiErrorToast';
import { useAiFeatures } from '@/hooks/useAiFeatures';
import { severityDotColor } from '@/lib/editorial-constants';
import { broadcastChapterDataChanged, cn, jsonFetchHeaders } from '@/lib/utils';
import type { Book, Chapter, ChapterEditorialFinding } from '@/types/models';

function DescriptionText({ children }: { children: React.ReactNode }) {
    return (
        <p className="text-[11px] leading-[1.4] text-ink-muted">{children}</p>
    );
}

export default function AiPanel({
    book,
    chapter,
    activeSceneId = null,
    onClose,
    onError,
    editorialChapterNote = null,
    editorialFindings = [],
    editorialReviewUrl,
    proseRunning = false,
    onProseStart,
    onProseEnd,
    gateWritingStyle,
}: {
    book: Book;
    chapter: Chapter;
    activeSceneId?: number | null;
    onClose: () => void;
    onError?: (message: string) => void;
    editorialChapterNote?: string | null;
    editorialFindings?: ChapterEditorialFinding[];
    editorialReviewUrl: string;
    proseRunning?: boolean;
    onProseStart?: (chapterId: number) => void;
    onProseEnd?: (chapterId: number) => void;
    /** Writing-style pre-flight wrapper for prose-generating actions. */
    gateWritingStyle?: (action: () => void) => void;
}) {
    const { t, i18n } = useTranslation('ai');
    const pageUrl = usePage().url;
    const { visible, usable } = useAiFeatures();
    const aiEnabled = usable;
    const showAiErrorToast = useAiErrorToast();

    // Local flags so each button shows the correct label while in flight; the
    // proseRunning prop is used to gate all three (only one revise can run at
    // a time) and to disable the inactive buttons without flipping labels.
    const [isRunningProse, setIsRunningProse] = useState(false);
    const [isRunningSceneProse, setIsRunningSceneProse] = useState(false);
    const [isRewriting, setIsRewriting] = useState(false);
    const [isStructuring, setIsStructuring] = useState(false);
    const [structureProposal, setStructureProposal] =
        useState<SceneStructureProposal | null>(null);

    // Chapter ref so the toast's "Compare" action resolves the post-revise
    // current version id at click time, after softRefresh has updated props.
    const chapterRef = useRef(chapter);
    chapterRef.current = chapter;

    const showRevisedToast = useCallback(() => {
        toast(
            t('proseRevise.toast.title', {
                ns: 'editor',
                defaultValue: 'AI revision applied',
            }),
            {
                description: t('proseRevise.toast.description', {
                    ns: 'editor',
                    defaultValue: 'The chapter now uses the revised version.',
                }),
                action: {
                    label: t('proseRevise.toast.compare', {
                        ns: 'editor',
                        defaultValue: 'Compare',
                    }),
                    onClick: async () => {
                        const versionId =
                            chapterRef.current.current_version?.id;
                        if (!versionId) return;
                        if (
                            typeof window !== 'undefined' &&
                            window.Native?.on
                        ) {
                            try {
                                const response = await fetch(
                                    openDiffWindow.url({
                                        book: book.id,
                                        chapter: chapterRef.current.id,
                                        version: versionId,
                                    }),
                                    {
                                        method: 'POST',
                                        headers: jsonFetchHeaders(),
                                    },
                                );
                                if (response.ok) return;
                            } catch {
                                /* native window unavailable */
                            }
                        }
                    },
                },
            },
        );
    }, [book.id, t]);

    const runRevision = useCallback(
        async (
            url: string,
            setBusy: (busy: boolean) => void,
            failMessage: string,
        ) => {
            setBusy(true);
            onProseStart?.(chapter.id);
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        ...jsonFetchHeaders(),
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({
                        expected_current_version_id:
                            chapterRef.current.current_version?.id ?? null,
                    }),
                });

                if (!response.ok) {
                    if (response.status === 409) {
                        showAiErrorToast({ kind: 'stale_version' });
                        return;
                    }
                    if (response.status === 422) {
                        const body = await response.json().catch(() => null);
                        throw new Error(body?.message ?? failMessage);
                    }
                    throw new Error(failMessage);
                }

                await response.text();
                broadcastChapterDataChanged(chapter.id);
                showRevisedToast();
            } catch (e) {
                onError?.(e instanceof Error ? e.message : failMessage);
            } finally {
                setBusy(false);
                onProseEnd?.(chapter.id);
            }
        },
        [
            chapter.id,
            onError,
            onProseStart,
            onProseEnd,
            showRevisedToast,
            showAiErrorToast,
        ],
    );

    const confirmLongChapter = useCallback(() => {
        if (chapter.word_count <= 8000) return true;
        return confirm(
            t('confirm.longChapter', {
                wordCount: chapter.word_count.toLocaleString(i18n.language),
            }),
        );
    }, [chapter.word_count, i18n.language, t]);

    const gated = useCallback(
        (action: () => void) =>
            gateWritingStyle ? gateWritingStyle(action) : action(),
        [gateWritingStyle],
    );

    const handleRunProse = useCallback(() => {
        if (!confirmLongChapter()) return;
        gated(() =>
            runRevision(
                revise.url({ book: book.id, chapter: chapter.id }),
                setIsRunningProse,
                t('error.prosePassFailed'),
            ),
        );
    }, [book.id, chapter.id, confirmLongChapter, gated, runRevision, t]);

    const handleRunSceneProse = useCallback(() => {
        if (!activeSceneId) return;
        gated(() =>
            runRevision(
                reviseScene.url({
                    book: book.id,
                    chapter: chapter.id,
                    scene: activeSceneId,
                }),
                setIsRunningSceneProse,
                t('error.prosePassFailed'),
            ),
        );
    }, [activeSceneId, book.id, chapter.id, gated, runRevision, t]);

    const handleEditorialRewrite = useCallback(() => {
        if (!confirmLongChapter()) return;
        gated(() =>
            runRevision(
                reviseWithEditorialFeedback.url({
                    book: book.id,
                    chapter: chapter.id,
                }),
                setIsRewriting,
                t('error.rewriteFailed'),
            ),
        );
    }, [book.id, chapter.id, confirmLongChapter, gated, runRevision, t]);

    const handleStructureScenes = useCallback(async () => {
        if (!confirmLongChapter()) return;
        setIsStructuring(true);
        onProseStart?.(chapter.id);
        try {
            const response = await fetch(
                suggestSceneStructure.url({
                    book: book.id,
                    chapter: chapter.id,
                }),
                {
                    method: 'POST',
                    headers: {
                        ...jsonFetchHeaders(),
                        Accept: 'application/json',
                    },
                },
            );

            if (!response.ok) {
                const body = await response.json().catch(() => null);
                throw new Error(
                    body?.message ?? t('structure.error.suggestFailed'),
                );
            }

            setStructureProposal(
                (await response.json()) as SceneStructureProposal,
            );
        } catch (e) {
            onError?.(
                e instanceof Error
                    ? e.message
                    : t('structure.error.suggestFailed'),
            );
        } finally {
            setIsStructuring(false);
            onProseEnd?.(chapter.id);
        }
    }, [
        book.id,
        chapter.id,
        confirmLongChapter,
        onError,
        onProseStart,
        onProseEnd,
        t,
    ]);

    const hasEditorialFeedback =
        !!editorialChapterNote || editorialFindings.length > 0;

    if (!visible) return null;

    return (
        <aside className="flex h-full shrink-0 flex-col border-l border-border-light bg-surface-sidebar">
            <PanelHeader
                title={t('headerTitle')}
                icon={<Sparkles size={14} className="text-ink-muted" />}
                onClose={onClose}
                suffix={
                    <span
                        className={cn(
                            'size-1.5 rounded-full',
                            aiEnabled ? 'bg-ai-green' : 'bg-status-revised',
                        )}
                    />
                }
            />

            <div className="flex flex-1 flex-col gap-6 overflow-x-hidden overflow-y-auto p-5">
                {/* Actions */}
                <div className="flex flex-col gap-2.5">
                    <SectionLabel>{t('section.prose')}</SectionLabel>
                    {aiEnabled ? (
                        <>
                            <Button
                                type="button"
                                variant="primary"
                                onClick={handleRunProse}
                                disabled={proseRunning}
                                className="w-full"
                            >
                                <PenTool size={14} strokeWidth={2.5} />
                                {isRunningProse
                                    ? t('prose.running')
                                    : t('prose.runProsePass')}
                            </Button>
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={handleRunSceneProse}
                                disabled={proseRunning || !activeSceneId}
                                className="w-full"
                            >
                                <PenTool size={14} strokeWidth={2.5} />
                                {isRunningSceneProse
                                    ? t('prose.runningScene')
                                    : t('prose.runProsePassScene')}
                            </Button>
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={handleEditorialRewrite}
                                disabled={proseRunning || !hasEditorialFeedback}
                                className="w-full"
                            >
                                <Wand2 size={14} strokeWidth={2.5} />
                                {isRewriting
                                    ? t('editorial.rewriting')
                                    : t('editorial.rewrite')}
                            </Button>
                            <DescriptionText>
                                {t('prose.description')}
                            </DescriptionText>
                            <DescriptionText>
                                {t('editorial.rewriteDescription')}
                            </DescriptionText>
                            <Link
                                href={settingsIndex.url({
                                    query: {
                                        from: pageUrl,
                                        section: 'revision-rules',
                                    },
                                })}
                                className="text-[11px] font-medium text-accent transition-colors hover:text-accent-dark"
                            >
                                {t('prose.settingsLink')}
                            </Link>
                        </>
                    ) : (
                        <DescriptionText>
                            {t('actions.notConfigured')}{' '}
                            <Link
                                href={settingsIndex.url({
                                    query: { from: pageUrl },
                                })}
                                className="font-medium text-accent underline decoration-accent/30 hover:decoration-accent"
                            >
                                {t('actions.configureSettings')}
                            </Link>
                        </DescriptionText>
                    )}
                </div>

                {/* Scene structure */}
                {aiEnabled && (
                    <div className="flex flex-col gap-2.5">
                        <SectionLabel>{t('section.structure')}</SectionLabel>
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={handleStructureScenes}
                            disabled={proseRunning}
                            className="w-full"
                        >
                            <ListTree size={14} strokeWidth={2.5} />
                            {isStructuring
                                ? t('structure.running')
                                : t('structure.run')}
                        </Button>
                        <DescriptionText>
                            {t('structure.description')}
                        </DescriptionText>
                    </div>
                )}

                {/* Editorial notes for this chapter */}
                <div className="flex flex-col gap-2.5">
                    <SectionLabel>{t('section.editorialNotes')}</SectionLabel>
                    {hasEditorialFeedback ? (
                        <>
                            {editorialChapterNote && (
                                <p className="text-[13px] leading-relaxed whitespace-pre-line text-ink-soft">
                                    {editorialChapterNote}
                                </p>
                            )}
                            {editorialFindings.length > 0 && (
                                <div className="flex flex-col gap-2.5">
                                    {editorialFindings.map((finding) => (
                                        <div
                                            key={finding.key}
                                            className="flex gap-2"
                                        >
                                            <span
                                                className={cn(
                                                    'mt-1.5 size-1.5 shrink-0 rounded-full',
                                                    severityDotColor[
                                                        finding.severity
                                                    ],
                                                )}
                                            />
                                            <div className="flex min-w-0 flex-col gap-0.5">
                                                <span className="text-[11px] leading-[1.4] text-ink-soft">
                                                    {finding.description}
                                                </span>
                                                {finding.recommendation && (
                                                    <span className="text-[11px] leading-[1.4] text-ink-faint">
                                                        {finding.recommendation}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                            <Link
                                href={editorialReviewUrl}
                                className="text-[11px] font-medium text-accent transition-colors hover:text-accent-dark"
                            >
                                {t('editorial.viewReport')}
                            </Link>
                        </>
                    ) : (
                        <div className="flex flex-col items-center gap-3 py-6 text-center">
                            <FileSearch size={20} className="text-ink-faint" />
                            <p className="text-[11px] leading-[1.4] text-ink-muted">
                                {t('editorial.none')}
                            </p>
                            <Link
                                href={editorialReviewUrl}
                                className="text-[11px] font-medium text-accent hover:underline"
                            >
                                {t('editorial.viewReport')}
                            </Link>
                        </div>
                    )}
                </div>
            </div>

            {structureProposal && (
                <SceneStructureDialog
                    book={book}
                    chapter={chapter}
                    proposal={structureProposal}
                    onClose={() => setStructureProposal(null)}
                />
            )}
        </aside>
    );
}
