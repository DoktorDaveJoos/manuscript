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
    BleedMode,
    ChapterHeading,
    ChapterRow,
    FontPairingDef,
    Format,
    MatterItem,
    SavedExportSettings,
    SceneBreakStyleDef,
    StorylineRef,
    TemplateDef,
    TrimSizeOption,
} from '@/components/export/types';
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
    trimSizes,
    templates,
    fontPairings,
    sceneBreakStyles,
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

    // Template & customization
    const defaultTemplate = templates[0];
    const [template, setTemplate] = useState(() =>
        saved.template && templates.some((t) => t.slug === saved.template)
            ? saved.template
            : (defaultTemplate?.slug ?? 'classic'),
    );
    const [fontPairing, setFontPairing] = useState(
        saved.font_pairing ??
            defaultTemplate?.defaultFontPairing ??
            'classic-serif',
    );
    const [sceneBreakStyle, setSceneBreakStyle] = useState(
        saved.scene_break_style ??
            defaultTemplate?.defaultSceneBreakStyle ??
            'asterisks',
    );
    const [dropCaps, setDropCaps] = useState(
        saved.drop_caps ?? defaultTemplate?.defaultDropCaps ?? true,
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
    const [chapterHeading, setChapterHeading] = useState<ChapterHeading>(
        saved.chapter_heading ?? 'full',
    );
    const [includeActBreaks, setIncludeActBreaks] = useState(
        saved.include_act_breaks ?? false,
    );
    const [showPageNumbers, setShowPageNumbers] = useState(
        saved.show_page_numbers ?? true,
    );
    const [trimSize, setTrimSize] = useState(() =>
        saved.trim_size === 'custom' ||
        trimSizes.some((t) => t.value === saved.trim_size)
            ? saved.trim_size!
            : '6x9',
    );
    const [fontSize, setFontSize] = useState(saved.font_size ?? 11);
    const [cmyk, setCmyk] = useState(saved.cmyk ?? false);
    const [bleed, setBleed] = useState(saved.bleed ?? 0);
    const [bleedMode, setBleedMode] = useState<BleedMode>(
        saved.bleed_mode ?? 'all',
    );
    const [customWidth, setCustomWidth] = useState(saved.custom_width ?? 130);
    const [customHeight, setCustomHeight] = useState(
        saved.custom_height ?? 190,
    );
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
            font_pairing: fontPairing,
            scene_break_style: sceneBreakStyle,
            drop_caps: dropCaps,
            chapter_heading: chapterHeading,
            include_act_breaks: includeActBreaks,
            show_page_numbers: showPageNumbers,
            trim_size: trimSize,
            font_size: fontSize,
            cmyk,
            bleed,
            bleed_mode: bleedMode,
            custom_width: customWidth,
            custom_height: customHeight,
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
            fontPairing,
            sceneBreakStyle,
            dropCaps,
            chapterHeading,
            includeActBreaks,
            showPageNumbers,
            trimSize,
            fontSize,
            cmyk,
            bleed,
            bleedMode,
            customWidth,
            customHeight,
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
        setExportError(null);

        const checkedOrdered = orderedChapters
            .filter((ch) => selectedChapterIds.has(ch.id))
            .map((ch) => ch.id);

        const data: Record<string, unknown> = {
            format,
            template,
            chapter_ids: checkedOrdered,
            chapter_heading: chapterHeading,
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
            data.cmyk = cmyk;
            data.bleed = bleed;
            data.bleed_mode = bleedMode;
            if (trimSize === 'custom') {
                data.custom_width = customWidth;
                data.custom_height = customHeight;
            }
        }

        data.front_matter = frontMatter
            .filter((i) => i.checked)
            .map((i) => i.id);
        data.back_matter = backMatter.filter((i) => i.checked).map((i) => i.id);

        downloadExport(book, data)
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
        orderedChapters,
        selectedChapterIds,
        chapterHeading,
        includeActBreaks,
        showPageNumbers,
        includeToc,
        trimSize,
        fontSize,
        cmyk,
        bleed,
        bleedMode,
        customWidth,
        customHeight,
        frontMatter,
        backMatter,
        fontPairing,
        sceneBreakStyle,
        dropCaps,
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
                    format={format}
                    onFormatChange={setFormat}
                    template={template}
                    onTemplateChange={handleTemplateChange}
                    trimSize={trimSize}
                    onTrimSizeChange={setTrimSize}
                    fontSize={fontSize}
                    onFontSizeChange={setFontSize}
                    cmyk={cmyk}
                    onCmykChange={setCmyk}
                    bleed={bleed}
                    onBleedChange={setBleed}
                    bleedMode={bleedMode}
                    onBleedModeChange={setBleedMode}
                    customWidth={customWidth}
                    onCustomWidthChange={setCustomWidth}
                    customHeight={customHeight}
                    onCustomHeightChange={setCustomHeight}
                    trimSizes={trimSizes}
                    chapterHeading={chapterHeading}
                    onChapterHeadingChange={setChapterHeading}
                    includeActBreaks={includeActBreaks}
                    onIncludeActBreaksChange={handleToggleActBreaks}
                    showPageNumbers={showPageNumbers}
                    onShowPageNumbersChange={handleTogglePageNumbers}
                    exporting={exporting}
                    onExport={handleExport}
                    error={exportError}
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
                    customWidth={customWidth}
                    customHeight={customHeight}
                    fontSize={fontSize}
                    chapterHeading={chapterHeading}
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
                />
            </div>
        </>
    );
}
