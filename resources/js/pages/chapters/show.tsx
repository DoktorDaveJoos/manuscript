import { beautify } from '@/actions/App/Http/Controllers/AiController';
import { updateContent, updateTitle } from '@/actions/App/Http/Controllers/ChapterController';
import NormalizePreview from '@/components/dashboard/NormalizePreview';
import AiPanel from '@/components/editor/AiPanel';
import EditorBar, { type SaveStatus } from '@/components/editor/EditorBar';
import FormattingToolbar from '@/components/editor/FormattingToolbar';
import Sidebar from '@/components/editor/Sidebar';
import VersionHistoryOverlay from '@/components/editor/VersionHistoryOverlay';
import WritingSurface from '@/components/editor/WritingSurface';
import useChapterEditor from '@/hooks/useChapterEditor';
import { useLicense } from '@/hooks/useLicense';
import { getXsrfToken } from '@/lib/csrf';
import type { Book, Chapter, Character, CharacterChapterPivot } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import { useCallback, useRef, useState } from 'react';

type ChapterWithRelations = Chapter & {
    characters?: (Character & { pivot: CharacterChapterPivot })[];
};

export default function ChapterShow({
    book,
    chapter,
    versionCount,
}: {
    book: Book;
    chapter: ChapterWithRelations;
    versionCount: number;
}) {
    const { isActive: isLicensed } = useLicense();
    const [saveStatus, setSaveStatus] = useState<SaveStatus>('saved');
    const [wordCount, setWordCount] = useState(chapter.word_count);
    const [showVersions, setShowVersions] = useState(false);
    const [showNormalize, setShowNormalize] = useState(false);
    const [isBeautifying, setIsBeautifying] = useState(false);
    const [isAiPanelOpen, setIsAiPanelOpen] = useState(() => {
        try {
            return localStorage.getItem('manuscript:ai-panel-open') !== 'false';
        } catch {
            return true;
        }
    });

    const toggleAiPanel = useCallback(() => {
        setIsAiPanelOpen((prev) => {
            const next = !prev;
            try {
                localStorage.setItem('manuscript:ai-panel-open', String(next));
            } catch {
                // Ignore storage errors
            }
            return next;
        });
    }, []);

    const abortRef = useRef<AbortController | null>(null);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const pendingContentRef = useRef<string | null>(null);

    const titleAbortRef = useRef<AbortController | null>(null);
    const titleTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const pendingTitleRef = useRef<string | null>(null);

    const flushContentSave = useCallback(async () => {
        if (timerRef.current) {
            clearTimeout(timerRef.current);
            timerRef.current = null;
        }

        const content = pendingContentRef.current;
        if (content === null) return;
        pendingContentRef.current = null;

        abortRef.current?.abort();
        const controller = new AbortController();
        abortRef.current = controller;

        setSaveStatus('saving');

        try {
            const response = await fetch(updateContent.url({ book: book.id, chapter: chapter.id }), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
                body: JSON.stringify({ content }),
                signal: controller.signal,
            });

            if (!response.ok) throw new Error('Save failed');

            const data = await response.json();
            setWordCount(data.word_count);
            setSaveStatus('saved');
        } catch (e) {
            if ((e as Error).name !== 'AbortError') {
                setSaveStatus('error');
            }
        }
    }, [book.id, chapter.id]);

    const flushTitleSave = useCallback(async () => {
        if (titleTimerRef.current) {
            clearTimeout(titleTimerRef.current);
            titleTimerRef.current = null;
        }

        const title = pendingTitleRef.current;
        if (title === null) return;
        pendingTitleRef.current = null;

        titleAbortRef.current?.abort();
        const controller = new AbortController();
        titleAbortRef.current = controller;

        setSaveStatus('saving');

        try {
            const response = await fetch(updateTitle.url({ book: book.id, chapter: chapter.id }), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
                body: JSON.stringify({ title }),
                signal: controller.signal,
            });

            if (!response.ok) throw new Error('Save failed');

            setSaveStatus('saved');
        } catch (e) {
            if ((e as Error).name !== 'AbortError') {
                setSaveStatus('error');
            }
        }
    }, [book.id, chapter.id]);

    const handleEditorUpdate = useCallback(
        (html: string, words: number) => {
            setWordCount(words);
            setSaveStatus('unsaved');
            pendingContentRef.current = html;

            if (timerRef.current) {
                clearTimeout(timerRef.current);
            }

            timerRef.current = setTimeout(() => {
                flushContentSave();
            }, 1500);
        },
        [flushContentSave],
    );

    const handleTitleUpdate = useCallback(
        (title: string) => {
            setSaveStatus('unsaved');
            pendingTitleRef.current = title;

            if (titleTimerRef.current) {
                clearTimeout(titleTimerRef.current);
            }

            titleTimerRef.current = setTimeout(() => {
                flushTitleSave();
            }, 1500);
        },
        [flushTitleSave],
    );

    const handleBeforeNavigate = useCallback(async () => {
        await Promise.all([flushContentSave(), flushTitleSave()]);
    }, [flushContentSave, flushTitleSave]);

    const handleBeautify = useCallback(async () => {
        setIsBeautifying(true);
        try {
            const response = await fetch(beautify.url({ book: book.id, chapter: chapter.id }), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
            });

            if (!response.ok) throw new Error('Beautify failed');

            router.reload();
        } catch {
            setSaveStatus('error');
        } finally {
            setIsBeautifying(false);
        }
    }, [book.id, chapter.id]);

    const editor = useChapterEditor({
        content: chapter.current_version?.content ?? '',
        onUpdate: handleEditorUpdate,
    });

    const povCharacterName = chapter.pov_character?.name ?? null;
    const timelineLabel = chapter.storyline?.timeline_label ?? null;

    return (
        <>
            <Head title={`${chapter.title} — ${book.title}`} />
            <div className="flex h-screen overflow-hidden bg-surface">
                <Sidebar
                    book={book}
                    storylines={book.storylines ?? []}
                    activeChapterId={chapter.id}
                    onBeforeNavigate={handleBeforeNavigate}
                />

                <div className="flex min-w-0 flex-1 flex-col">
                    <div className="relative">
                        <EditorBar
                            chapter={chapter}
                            storylineName={chapter.storyline?.name ?? 'Untitled storyline'}
                            wordCount={wordCount}
                            versionCount={versionCount}
                            saveStatus={saveStatus}
                            onVersionClick={() => setShowVersions(!showVersions)}
                        />
                        {showVersions && (
                            <VersionHistoryOverlay
                                bookId={book.id}
                                chapterId={chapter.id}
                                onClose={() => setShowVersions(false)}
                            />
                        )}
                    </div>

                    <FormattingToolbar
                        editor={editor}
                        onNormalizeClick={() => setShowNormalize(true)}
                        onBeautifyClick={handleBeautify}
                        aiEnabled={book.ai_enabled}
                        isBeautifying={isBeautifying}
                        licensed={isLicensed}
                    />

                    <WritingSurface
                        editor={editor}
                        title={chapter.title}
                        povCharacterName={povCharacterName}
                        timelineLabel={timelineLabel}
                        onTitleUpdate={handleTitleUpdate}
                    />
                </div>

                <AiPanel
                    characters={(chapter.characters as (Character & { pivot: CharacterChapterPivot })[]) ?? []}
                    book={book}
                    chapter={chapter}
                    isOpen={isAiPanelOpen}
                    onToggle={toggleAiPanel}
                    licensed={isLicensed}
                />
            </div>

            {showNormalize && (
                <NormalizePreview
                    bookId={book.id}
                    chapterId={chapter.id}
                    onClose={() => setShowNormalize(false)}
                />
            )}
        </>
    );
}
