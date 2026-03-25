import { Head } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { reorder as reorderChapters } from '@/actions/App/Http/Controllers/ChapterController';
import Sidebar from '@/components/editor/Sidebar';
import ExportPreview from '@/components/export/ExportPreview';
import ExportReadingOrder from '@/components/export/ExportReadingOrder';
import ExportSettings from '@/components/export/ExportSettings';
import type {
    ChapterRow,
    FontPairingDef,
    Format,
    MatterItem,
    SceneBreakStyleDef,
    StorylineRef,
    TemplateDef,
    TrimSizeOption,
} from '@/components/export/types';
import { useFreeTier } from '@/hooks/useFreeTier';
import { useSidebarStorylines } from '@/hooks/useSidebarStorylines';
import { downloadExport } from '@/lib/export-download';
import { jsonFetchHeaders } from '@/lib/utils';
import type { Book } from '@/types/models';

interface Props {
    book: Book;
    storylines: StorylineRef[];
    chapters: ChapterRow[];
    trimSizes: TrimSizeOption[];
    templates: TemplateDef[];
    fontPairings: FontPairingDef[];
    sceneBreakStyles: SceneBreakStyleDef[];
}

const GOOGLE_FONTS_URL =
    'https://fonts.googleapis.com/css2?family=Crimson+Pro:ital,wght@0,400;0,700;1,400;1,700&family=Source+Sans+3:wght@400;700&family=Source+Serif+4:ital,wght@0,400;0,700;1,400;1,700&family=Cormorant+Garamond:ital,wght@0,400;0,700;1,400;1,700&display=swap';

export default function Export({
    book,
    storylines,
    chapters,
    trimSizes,
    templates,
    fontPairings,
    sceneBreakStyles,
}: Props) {
    const { t } = useTranslation('export');
    const { isPro } = useFreeTier();
    const sidebarStorylines = useSidebarStorylines();

    // Epilogue detection
    const hasEpilogue = useMemo(
        () => chapters.some((ch) => ch.is_epilogue),
        [chapters],
    );

    // Format
    const [format, setFormat] = useState<Format>(isPro ? 'epub' : 'docx');

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
        () => new Set(chapters.map((ch) => ch.id)),
    );

    // Template & customization
    const defaultTemplate = templates[0];
    const [template, setTemplate] = useState(
        defaultTemplate?.slug ?? 'classic',
    );
    const [fontPairing, setFontPairing] = useState(
        defaultTemplate?.defaultFontPairing ?? 'classic-serif',
    );
    const [sceneBreakStyle, setSceneBreakStyle] = useState(
        defaultTemplate?.defaultSceneBreakStyle ?? 'asterisks',
    );
    const [dropCaps, setDropCaps] = useState(
        defaultTemplate?.defaultDropCaps ?? true,
    );

    const selectedTemplateDef = useMemo(
        () => templates.find((t) => t.slug === template) ?? templates[0],
        [templates, template],
    );

    const isCustomized = useMemo(() => {
        if (!selectedTemplateDef) return false;
        return (
            fontPairing !== selectedTemplateDef.defaultFontPairing ||
            sceneBreakStyle !== selectedTemplateDef.defaultSceneBreakStyle ||
            dropCaps !== selectedTemplateDef.defaultDropCaps
        );
    }, [selectedTemplateDef, fontPairing, sceneBreakStyle, dropCaps]);

    const handleTemplateChange = useCallback(
        (slug: string) => {
            setTemplate(slug);
            const def = templates.find((t) => t.slug === slug);
            if (def) {
                setFontPairing(def.defaultFontPairing);
                setSceneBreakStyle(def.defaultSceneBreakStyle);
                setDropCaps(def.defaultDropCaps);
            }
        },
        [templates],
    );

    // Options
    const [includeChapterTitles, setIncludeChapterTitles] = useState(true);
    const [includeActBreaks, setIncludeActBreaks] = useState(false);
    const [showPageNumbers, setShowPageNumbers] = useState(true);
    const [trimSize, setTrimSize] = useState('6x9');
    const [fontSize, setFontSize] = useState(11);
    const [includeCover, setIncludeCover] = useState(!!book.cover_image_path);
    const [exporting, setExporting] = useState(false);

    const hasCover = !!book.cover_image_path;

    // Front/back matter (visual only)
    const initialFrontMatter: MatterItem[] = useMemo(
        () => [
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
            {
                id: 'toc',
                label: t('frontMatter.tableOfContents'),
                checked: false,
            },
        ],
        [t],
    );

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

    const [frontMatter, setFrontMatter] = useState(initialFrontMatter);
    const [backMatter, setBackMatter] = useState(initialBackMatter);

    // If hasEpilogue changes, we need to reset back matter
    const [prevHasEpilogue, setPrevHasEpilogue] = useState(hasEpilogue);
    if (prevHasEpilogue !== hasEpilogue) {
        setPrevHasEpilogue(hasEpilogue);
        setBackMatter(initialBackMatter);
    }

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

    const visibleChapters = useMemo(
        () =>
            epilogueChecked
                ? orderedChapters.filter((ch) => !ch.is_epilogue)
                : orderedChapters,
        [orderedChapters, epilogueChecked],
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

    const handleToggleChapterTitles = useCallback(() => {
        setIncludeChapterTitles((prev) => !prev);
    }, []);

    const handleToggleActBreaks = useCallback(() => {
        setIncludeActBreaks((prev) => !prev);
    }, []);

    const handleTogglePageNumbers = useCallback(() => {
        setShowPageNumbers((prev) => !prev);
    }, []);

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

        const checkedOrdered = orderedChapters
            .filter((ch) => selectedChapterIds.has(ch.id))
            .map((ch) => ch.id);

        const data: Record<string, unknown> = {
            format,
            template,
            chapter_ids: checkedOrdered,
            include_chapter_titles: includeChapterTitles,
            include_act_breaks: includeActBreaks,
            include_table_of_contents: includeToc,
            show_page_numbers: showPageNumbers,
            font_pairing: fontPairing,
            scene_break_style: sceneBreakStyle,
            drop_caps: dropCaps,
            include_cover: includeCover,
        };

        if (format === 'pdf') {
            data.trim_size = trimSize;
            data.font_size = fontSize;
        }

        data.front_matter = frontMatter
            .filter((i) => i.checked)
            .map((i) => i.id);
        data.back_matter = backMatter.filter((i) => i.checked).map((i) => i.id);

        downloadExport(book, data)
            .catch(() => {})
            .finally(() => setExporting(false));
    }, [
        book,
        format,
        template,
        orderedChapters,
        selectedChapterIds,
        includeChapterTitles,
        includeActBreaks,
        showPageNumbers,
        includeToc,
        trimSize,
        fontSize,
        frontMatter,
        backMatter,
        fontPairing,
        sceneBreakStyle,
        dropCaps,
        includeCover,
    ]);

    return (
        <>
            <Head title={t('pageTitle', { bookTitle: book.title })}>
                <link rel="stylesheet" href={GOOGLE_FONTS_URL} />
            </Head>
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
                    format={format}
                    onFormatChange={setFormat}
                    template={template}
                    onTemplateChange={handleTemplateChange}
                    trimSize={trimSize}
                    onTrimSizeChange={setTrimSize}
                    fontSize={fontSize}
                    onFontSizeChange={setFontSize}
                    trimSizes={trimSizes}
                    includeChapterTitles={includeChapterTitles}
                    onIncludeChapterTitlesChange={handleToggleChapterTitles}
                    includeActBreaks={includeActBreaks}
                    onIncludeActBreaksChange={handleToggleActBreaks}
                    showPageNumbers={showPageNumbers}
                    onShowPageNumbersChange={handleTogglePageNumbers}
                    exporting={exporting}
                    onExport={handleExport}
                    templates={templates}
                    fontPairings={fontPairings}
                    sceneBreakStyles={sceneBreakStyles}
                    fontPairing={fontPairing}
                    onFontPairingChange={setFontPairing}
                    sceneBreakStyle={sceneBreakStyle}
                    onSceneBreakStyleChange={setSceneBreakStyle}
                    dropCaps={dropCaps}
                    onDropCapsChange={setDropCaps}
                    isCustomized={isCustomized}
                    includeCover={includeCover}
                    onIncludeCoverChange={setIncludeCover}
                    hasCover={hasCover}
                />

                <ExportPreview
                    bookId={book.id}
                    format={format}
                    trimSize={trimSize}
                    trimSizes={trimSizes}
                    fontSize={fontSize}
                    includeChapterTitles={includeChapterTitles}
                    showPageNumbers={showPageNumbers}
                    includeActBreaks={includeActBreaks}
                    selectedChapterIds={selectedChapterIds}
                    orderedChapters={orderedChapters}
                    frontMatter={frontMatter}
                    backMatter={backMatter}
                    template={template}
                    fontPairing={fontPairing}
                    sceneBreakStyle={sceneBreakStyle}
                    dropCaps={dropCaps}
                    includeCover={includeCover}
                />
            </div>
        </>
    );
}
