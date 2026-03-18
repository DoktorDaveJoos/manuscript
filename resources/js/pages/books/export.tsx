import { Head } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { reorder as reorderChapters } from '@/actions/App/Http/Controllers/ChapterController';
import Sidebar from '@/components/editor/Sidebar';
import ExportPreview from '@/components/export/ExportPreview';
import ExportReadingOrder from '@/components/export/ExportReadingOrder';
import ExportSettings from '@/components/export/ExportSettings';
import type { Format } from '@/components/export/ExportSettings';
import type {
    ActRef,
    ChapterRow,
    MatterItem,
    StorylineRef,
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
    acts: ActRef[];
}

const INITIAL_FRONT_MATTER: MatterItem[] = [
    { id: 'title-page', label: 'Title Page', checked: true },
    { id: 'copyright', label: 'Copyright', checked: true },
    { id: 'dedication', label: 'Dedication', checked: false },
    { id: 'toc', label: 'Table of Contents', checked: false },
];

const INITIAL_BACK_MATTER: MatterItem[] = [
    { id: 'also-by', label: 'Also By', checked: true },
    { id: 'acknowledgments', label: 'Acknowledgments', checked: false },
    { id: 'about-author', label: 'About the Author', checked: false },
];

export default function Export({
    book,
    storylines,
    chapters,
    trimSizes,
    acts,
}: Props) {
    const { t } = useTranslation('export');
    const sidebarStorylines = useSidebarStorylines();

    // Format
    const [format, setFormat] = useState<Format>('epub');

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

    // Options
    const [includeChapterTitles, setIncludeChapterTitles] = useState(true);
    const [includeActBreaks, setIncludeActBreaks] = useState(false);
    const [includeTableOfContents, setIncludeTableOfContents] = useState(false);
    const [trimSize, setTrimSize] = useState('6x9');
    const [fontSize, setFontSize] = useState(11);
    const [exporting, setExporting] = useState(false);

    // Front/back matter (visual only)
    const [frontMatter, setFrontMatter] = useState(INITIAL_FRONT_MATTER);
    const [backMatter, setBackMatter] = useState(INITIAL_BACK_MATTER);

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

    const handleToggleTableOfContents = useCallback(() => {
        setIncludeTableOfContents((prev) => !prev);
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
            chapter_ids: checkedOrdered,
            include_chapter_titles: includeChapterTitles,
            include_act_breaks: includeActBreaks,
            include_table_of_contents: includeTableOfContents,
        };

        if (format === 'pdf') {
            data.trim_size = trimSize;
            data.font_size = fontSize;
        }

        downloadExport(book, data)
            .catch(() => {})
            .finally(() => setExporting(false));
    }, [
        book,
        format,
        orderedChapters,
        selectedChapterIds,
        includeChapterTitles,
        includeActBreaks,
        includeTableOfContents,
        trimSize,
        fontSize,
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
                    storylines={storylines}
                    selectedChapterIds={selectedChapterIds}
                    onToggleChapter={handleToggleChapter}
                    orderedChapters={orderedChapters}
                    onReorder={handleReorder}
                    frontMatter={frontMatter}
                    onToggleFrontMatter={handleToggleFrontMatter}
                    backMatter={backMatter}
                    onToggleBackMatter={handleToggleBackMatter}
                />

                <ExportSettings
                    format={format}
                    onFormatChange={setFormat}
                    trimSize={trimSize}
                    onTrimSizeChange={setTrimSize}
                    fontSize={fontSize}
                    onFontSizeChange={setFontSize}
                    trimSizes={trimSizes}
                    includeChapterTitles={includeChapterTitles}
                    onIncludeChapterTitlesChange={handleToggleChapterTitles}
                    includeActBreaks={includeActBreaks}
                    onIncludeActBreaksChange={handleToggleActBreaks}
                    includeTableOfContents={includeTableOfContents}
                    onIncludeTableOfContentsChange={handleToggleTableOfContents}
                    exporting={exporting}
                    onExport={handleExport}
                />

                <ExportPreview
                    bookTitle={book.title}
                    format={format}
                    trimSize={trimSize}
                    fontSize={fontSize}
                    includeChapterTitles={includeChapterTitles}
                    showPageNumbers={includeTableOfContents}
                    includeActBreaks={includeActBreaks}
                    selectedChapterIds={selectedChapterIds}
                    orderedChapters={orderedChapters}
                    frontMatter={frontMatter}
                    backMatter={backMatter}
                    acts={acts}
                />
            </div>
        </>
    );
}
