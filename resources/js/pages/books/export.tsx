import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { updateExportSettings } from '@/actions/App/Http/Controllers/BookSettingsController';
import { reorder as reorderChapters } from '@/actions/App/Http/Controllers/ChapterController';
import Sidebar from '@/components/editor/Sidebar';
import ExportPreview from '@/components/export/ExportPreview';
import ExportReadingOrder from '@/components/export/ExportReadingOrder';
import ExportSettings from '@/components/export/ExportSettings';
import type {
    ChapterRow,
    DocxLayout,
    Format,
    MatterItem,
    SavedExportSettings,
    StorylineRef,
    TemplateDef,
} from '@/components/export/types';
import { useSidebarStorylines } from '@/hooks/useSidebarStorylines';
import { track } from '@/lib/analytics';
import { downloadExport } from '@/lib/export-download';
import { jsonFetchHeaders } from '@/lib/utils';
import type { Book } from '@/types/models';

interface Props {
    book: Book;
    storylines: StorylineRef[];
    chapters: ChapterRow[];
    templates: TemplateDef[];
    currentTemplate: string;
    exportSettings: SavedExportSettings | null;
}

function applySavedChecks(
    items: MatterItem[],
    savedIds: string[] | undefined,
): MatterItem[] {
    if (!savedIds) return items;
    return items.map((item) => ({
        ...item,
        checked: savedIds.includes(item.id),
    }));
}

export default function Export({
    book,
    storylines,
    chapters,
    templates,
    currentTemplate,
    exportSettings,
}: Props) {
    const { t } = useTranslation('export');
    const sidebarStorylines = useSidebarStorylines();

    // Saved UI selections from the last visit (books.export_settings)
    const saved = useMemo<SavedExportSettings>(
        () => exportSettings ?? {},
        [exportSettings],
    );

    // Epilogue detection
    const hasEpilogue = useMemo(
        () => chapters.some((ch) => ch.is_epilogue),
        [chapters],
    );

    // Prologue detection
    const hasPrologue = useMemo(
        () => chapters.some((ch) => ch.is_prologue),
        [chapters],
    );

    // Format
    const [format, setFormat] = useState<Format>(saved.format ?? 'epub');

    // DOCX page layout (international manuscript vs. German Normseite)
    const [docxLayout, setDocxLayout] = useState<DocxLayout>(
        saved.docx_layout ?? 'manuscript',
    );

    // Chapters
    const sortedFromProps = useMemo(
        () => [...chapters].sort((a, b) => a.reader_order - b.reader_order),
        [chapters],
    );
    const [orderedChapters, setOrderedChapters] = useState(sortedFromProps);
    const [prevSorted, setPrevSorted] = useState(sortedFromProps);
    if (prevSorted !== sortedFromProps) {
        setPrevSorted(sortedFromProps);
        setOrderedChapters(sortedFromProps);
    }

    const [selectedChapterIds, setSelectedChapterIds] = useState<Set<number>>(
        () => {
            const excluded = new Set(saved.excluded_chapter_ids ?? []);
            return new Set(
                chapters
                    .filter((ch) => !excluded.has(ch.id))
                    .map((ch) => ch.id),
            );
        },
    );

    // Template — the book's applied Book Designer template wins over the
    // saved page snapshot; typesetting itself lives in the designer.
    const [template, setTemplate] = useState(() => {
        const known = (slug: string | undefined) =>
            slug !== undefined && templates.some((tpl) => tpl.slug === slug);
        if (known(currentTemplate)) return currentTemplate;
        if (known(saved.template)) return saved.template!;
        return templates[0]?.slug ?? 'classic';
    });

    const selectedTemplateDef = useMemo(
        () => templates.find((tpl) => tpl.slug === template),
        [templates, template],
    );

    // Options
    const [cmyk, setCmyk] = useState(saved.cmyk ?? false);
    const [includeCover, setIncludeCover] = useState(
        (saved.include_cover ?? true) && !!book.cover_image_path,
    );
    const [exporting, setExporting] = useState(false);
    const [exportError, setExportError] = useState<string | null>(null);

    const hasCover = !!book.cover_image_path;

    // Front/back matter (visual only)
    const initialFrontMatter: MatterItem[] = useMemo(() => {
        const items: MatterItem[] = [
            {
                id: 'title-page',
                label: t('frontMatter.titlePage'),
                checked: true,
            },
            {
                id: 'copyright',
                label: t('frontMatter.copyright'),
                checked: true,
            },
            {
                id: 'dedication',
                label: t('frontMatter.dedication'),
                checked: false,
            },
            {
                id: 'epigraph',
                label: t('frontMatter.epigraph'),
                checked: false,
            },
        ];
        if (hasPrologue) {
            items.push({
                id: 'prologue',
                label: t('frontMatter.prologue'),
                checked: false,
            });
        }
        items.push({
            id: 'toc',
            label: t('frontMatter.tableOfContents'),
            checked: false,
        });
        return items;
    }, [t, hasPrologue]);

    const initialBackMatter: MatterItem[] = useMemo(() => {
        const items: MatterItem[] = [];
        if (hasEpilogue) {
            items.push({
                id: 'epilogue',
                label: t('backMatter.epilogue'),
                checked: false,
            });
        }
        items.push(
            {
                id: 'acknowledgments',
                label: t('backMatter.acknowledgments'),
                checked: false,
                settingsSection: 'acknowledgment',
            },
            {
                id: 'about-author',
                label: t('backMatter.aboutTheAuthor'),
                checked: false,
                settingsSection: 'about-author',
            },
            {
                id: 'also-by',
                label: t('backMatter.alsoBy'),
                checked: false,
            },
        );
        return items;
    }, [t, hasEpilogue]);

    const [frontMatter, setFrontMatter] = useState(() =>
        applySavedChecks(initialFrontMatter, saved.front_matter),
    );
    const [backMatter, setBackMatter] = useState(() =>
        applySavedChecks(initialBackMatter, saved.back_matter),
    );

    // If hasEpilogue changes, we need to reset back matter
    const [prevHasEpilogue, setPrevHasEpilogue] = useState(hasEpilogue);
    if (prevHasEpilogue !== hasEpilogue) {
        setPrevHasEpilogue(hasEpilogue);
        setBackMatter(initialBackMatter);
    }

    // If hasPrologue changes, we need to reset front matter
    const [prevHasPrologue, setPrevHasPrologue] = useState(hasPrologue);
    if (prevHasPrologue !== hasPrologue) {
        setPrevHasPrologue(hasPrologue);
        setFrontMatter(initialFrontMatter);
    }

    // Persist every selection change immediately so the page state survives
    // navigation and app restarts (no debounce — local SQLite, no network cost).
    const settingsSnapshot = useMemo<SavedExportSettings>(
        () => ({
            format,
            template,
            docx_layout: docxLayout,
            cmyk,
            include_cover: includeCover,
            front_matter: frontMatter.filter((i) => i.checked).map((i) => i.id),
            back_matter: backMatter.filter((i) => i.checked).map((i) => i.id),
            excluded_chapter_ids: chapters
                .filter((ch) => !selectedChapterIds.has(ch.id))
                .map((ch) => ch.id),
        }),
        [
            format,
            template,
            docxLayout,
            cmyk,
            includeCover,
            frontMatter,
            backMatter,
            chapters,
            selectedChapterIds,
        ],
    );

    const isInitialSnapshot = useRef(true);
    useEffect(() => {
        if (isInitialSnapshot.current) {
            isInitialSnapshot.current = false;
            return;
        }
        fetch(updateExportSettings.url(book.id), {
            method: 'PUT',
            headers: jsonFetchHeaders(),
            body: JSON.stringify({ settings: settingsSnapshot }),
        });
    }, [settingsSnapshot, book.id]);

    const includeToc = useMemo(
        () => frontMatter.find((item) => item.id === 'toc')?.checked ?? false,
        [frontMatter],
    );

    // When epilogue is checked in back matter, hide epilogue chapters from the list
    const epilogueChecked = useMemo(
        () =>
            backMatter.find((item) => item.id === 'epilogue')?.checked ?? false,
        [backMatter],
    );

    // When prologue is checked in front matter, hide prologue chapters from the list
    const prologueChecked = useMemo(
        () =>
            frontMatter.find((item) => item.id === 'prologue')?.checked ??
            false,
        [frontMatter],
    );

    const visibleChapters = useMemo(
        () =>
            orderedChapters.filter(
                (ch) =>
                    !(epilogueChecked && ch.is_epilogue) &&
                    !(prologueChecked && ch.is_prologue),
            ),
        [orderedChapters, epilogueChecked, prologueChecked],
    );

    const handleToggleChapter = useCallback((id: number) => {
        setSelectedChapterIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    }, []);

    const handleReorder = useCallback(
        (reordered: ChapterRow[]) => {
            setOrderedChapters(reordered);

            // Persist to backend
            fetch(reorderChapters.url(book.id), {
                method: 'POST',
                headers: jsonFetchHeaders(),
                body: JSON.stringify({
                    order: reordered.map((ch) => ({
                        id: ch.id,
                        storyline_id: ch.storyline_id,
                    })),
                }),
            });
        },
        [book.id],
    );

    const handleToggleFrontMatter = useCallback((id: string) => {
        setFrontMatter((prev) =>
            prev.map((item) =>
                item.id === id ? { ...item, checked: !item.checked } : item,
            ),
        );
    }, []);

    const handleToggleBackMatter = useCallback((id: string) => {
        setBackMatter((prev) =>
            prev.map((item) =>
                item.id === id ? { ...item, checked: !item.checked } : item,
            ),
        );
    }, []);

    const handleExport = useCallback(() => {
        setExporting(true);
        setExportError(null);

        const checkedOrdered = orderedChapters
            .filter((ch) => selectedChapterIds.has(ch.id))
            .map((ch) => ch.id);

        const data: Record<string, unknown> = {
            format,
            template,
            chapter_ids: checkedOrdered,
            include_table_of_contents: includeToc,
            include_cover: includeCover,
            front_matter: frontMatter.filter((i) => i.checked).map((i) => i.id),
            back_matter: backMatter.filter((i) => i.checked).map((i) => i.id),
        };

        if (format === 'pdf') {
            data.cmyk = cmyk;
        }

        if (format === 'docx') {
            data.docx_layout = docxLayout;
        }

        downloadExport(book, data)
            .then(() => track('book_exported'))
            .catch((err: unknown) => {
                setExportError(
                    err instanceof Error ? err.message : String(err),
                );
            })
            .finally(() => setExporting(false));
    }, [
        book,
        format,
        template,
        docxLayout,
        orderedChapters,
        selectedChapterIds,
        includeToc,
        cmyk,
        frontMatter,
        backMatter,
        includeCover,
    ]);

    return (
        <>
            <Head title={t('pageTitle', { bookTitle: book.title })} />
            <div className="flex h-screen overflow-hidden bg-surface">
                <Sidebar
                    book={book}
                    storylines={sidebarStorylines}
                    scenesVisible={false}
                    onScenesVisibleChange={() => {}}
                />

                <ExportReadingOrder
                    bookId={book.id}
                    storylines={storylines}
                    selectedChapterIds={selectedChapterIds}
                    onToggleChapter={handleToggleChapter}
                    orderedChapters={visibleChapters}
                    onReorder={handleReorder}
                    frontMatter={frontMatter}
                    onToggleFrontMatter={handleToggleFrontMatter}
                    backMatter={backMatter}
                    onToggleBackMatter={handleToggleBackMatter}
                />

                <ExportSettings
                    bookId={book.id}
                    format={format}
                    onFormatChange={setFormat}
                    template={template}
                    onTemplateChange={setTemplate}
                    templates={templates}
                    docxLayout={docxLayout}
                    onDocxLayoutChange={setDocxLayout}
                    cmyk={cmyk}
                    onCmykChange={setCmyk}
                    includeCover={includeCover}
                    onIncludeCoverChange={setIncludeCover}
                    hasCover={hasCover}
                    exporting={exporting}
                    onExport={handleExport}
                    error={exportError}
                />

                <ExportPreview
                    bookId={book.id}
                    format={format}
                    template={template}
                    templateDef={selectedTemplateDef}
                    selectedChapterIds={selectedChapterIds}
                    orderedChapters={orderedChapters}
                    frontMatter={frontMatter}
                    backMatter={backMatter}
                />
            </div>
        </>
    );
}
