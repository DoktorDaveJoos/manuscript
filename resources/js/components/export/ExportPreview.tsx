import { useTranslation } from 'react-i18next';
import type { Format } from '@/components/export/ExportSettings';
import type { ActRef, ChapterRow, MatterItem } from '@/components/export/types';
import { usePreviewPages } from '@/components/export/usePreviewPages';
import type { PreviewPage } from '@/components/export/usePreviewPages';
import { cn } from '@/lib/utils';

interface ExportPreviewProps {
    bookTitle: string;
    format: Format;
    trimSize: string;
    fontSize: number;
    includeChapterTitles: boolean;
    showPageNumbers: boolean;
    includeActBreaks: boolean;
    selectedChapterIds: Set<number>;
    orderedChapters: ChapterRow[];
    frontMatter: MatterItem[];
    backMatter: MatterItem[];
    acts: ActRef[];
}

const PAGE_WIDTH = 340;
const PAGE_HEIGHT = 473;

function PageShell({
    children,
    isMono,
}: {
    children: React.ReactNode;
    isMono?: boolean;
}) {
    return (
        <div
            className={cn(
                'flex shrink-0 flex-col overflow-hidden rounded-[3px] pt-10 pr-[36px] pb-8 pl-10 shadow-[0_6px_20px_#00000014]',
                isMono ? 'font-mono' : 'font-serif',
            )}
            style={{
                width: PAGE_WIDTH,
                height: PAGE_HEIGHT,
                backgroundColor: '#FFFEFA',
            }}
        >
            {children}
        </div>
    );
}

function TitlePage({
    bookTitle,
    titleSize,
}: {
    bookTitle: string;
    titleSize: string;
}) {
    return (
        <div className="flex flex-1 items-center justify-center">
            <h2
                className="text-center font-serif leading-tight text-neutral-900"
                style={{ fontSize: titleSize }}
            >
                {bookTitle}
            </h2>
        </div>
    );
}

function CopyrightPage({ bodySize }: { bodySize: string }) {
    const { t } = useTranslation('export');
    return (
        <div className="flex flex-1 flex-col justify-end">
            <p
                className="text-center leading-relaxed text-neutral-400"
                style={{ fontSize: bodySize }}
            >
                {t('preview.copyright')}
            </p>
        </div>
    );
}

function TocPage({
    chapterTitles,
    bodySize,
}: {
    chapterTitles: string[];
    bodySize: string;
}) {
    const { t } = useTranslation('export');
    return (
        <div className="flex flex-1 flex-col pt-4">
            <h3 className="mb-3 text-center text-[10px] font-semibold tracking-[0.15em] text-neutral-900 uppercase">
                {t('preview.contents')}
            </h3>
            <ul className="space-y-1 overflow-hidden">
                {chapterTitles.map((title, i) => (
                    <li
                        key={i}
                        className="truncate text-neutral-600"
                        style={{ fontSize: bodySize }}
                    >
                        {title}
                    </li>
                ))}
            </ul>
        </div>
    );
}

function DedicationPage({ bodySize }: { bodySize: string }) {
    const { t } = useTranslation('export');
    return (
        <div className="flex flex-1 items-center justify-center">
            <p
                className="text-center text-neutral-500 italic"
                style={{ fontSize: bodySize }}
            >
                {t('preview.dedication')}
            </p>
        </div>
    );
}

function ActBreakPage({
    actNumber,
    actTitle,
}: {
    actNumber: number;
    actTitle?: string;
}) {
    return (
        <div className="flex flex-1 flex-col items-center justify-center">
            <span className="text-[11px] font-semibold tracking-[0.2em] text-neutral-900 uppercase">
                Act {actNumber}
            </span>
            {actTitle && (
                <span className="mt-1 text-[9px] text-neutral-500">
                    {actTitle}
                </span>
            )}
        </div>
    );
}

function ChapterPage({
    page,
    format,
    showPageNumbers,
    pageNumber,
    bodySize,
    titleSize,
    dropCapSize,
}: {
    page: PreviewPage;
    format: Format;
    showPageNumbers: boolean;
    pageNumber: number;
    bodySize: string;
    titleSize: string;
    dropCapSize: string;
}) {
    const showRunningHeader = format === 'pdf';
    const showPageNum = showPageNumbers && format === 'pdf';
    const paragraphs = page.paragraphs ?? [];
    const firstChar = paragraphs[0]?.[0] ?? '';
    const firstParaRest = paragraphs[0]?.slice(1) ?? '';

    return (
        <div className="flex flex-1 flex-col overflow-hidden">
            {showRunningHeader && (
                <div className="mb-3 shrink-0 text-right text-[7px] tracking-widest text-[#B5B5B5] uppercase">
                    {page.title}
                </div>
            )}

            {page.showTitle && (
                <div className="shrink-0 pt-6 pb-5">
                    <div className="mb-1 text-center text-[7px] font-medium tracking-[0.2em] text-[#B5B5B5] uppercase">
                        Chapter {(page.chapterIndex ?? 0) + 1}
                    </div>
                    <h2
                        className="text-center leading-tight tracking-[-0.01em] text-ink"
                        style={{ fontSize: titleSize }}
                    >
                        {page.title}
                    </h2>
                </div>
            )}

            <div className="min-h-0 flex-1 overflow-hidden">
                {paragraphs.length > 0 ? (
                    <>
                        <p
                            className="leading-[1.75] text-[#4A4A4A]"
                            style={{ fontSize: bodySize }}
                        >
                            {page.isFirst && firstChar && (
                                <span
                                    className="float-left mt-0.5 mr-1 leading-[0.8] text-ink"
                                    style={{ fontSize: dropCapSize }}
                                >
                                    {firstChar}
                                </span>
                            )}
                            {page.isFirst ? firstParaRest : paragraphs[0]}
                        </p>
                        {paragraphs.slice(1).map((para, i) => (
                            <p
                                key={i}
                                className="mt-2 leading-[1.75] text-[#4A4A4A]"
                                style={{ fontSize: bodySize }}
                            >
                                {para}
                            </p>
                        ))}
                    </>
                ) : (
                    <p
                        className="leading-[1.75] text-neutral-400 italic"
                        style={{ fontSize: bodySize }}
                    >
                        No content yet.
                    </p>
                )}
            </div>

            {showPageNum && (
                <div className="mt-2 shrink-0 text-center text-[8px] text-[#B5B5B5]">
                    {pageNumber}
                </div>
            )}
        </div>
    );
}

function BackMatterPage({
    type,
    bodySize,
}: {
    type: string;
    bodySize: string;
}) {
    const { t } = useTranslation('export');
    const keyMap: Record<string, string> = {
        acknowledgments: 'preview.acknowledgments',
        'about-author': 'preview.aboutTheAuthor',
        'also-by': 'preview.alsoBy',
    };

    const heading = t(keyMap[type] ?? type);
    return (
        <div className="flex flex-1 flex-col pt-4">
            <h3 className="mb-3 text-center text-[10px] font-semibold tracking-[0.15em] text-neutral-900 uppercase">
                {heading}
            </h3>
            <p
                className="text-center leading-relaxed text-neutral-400"
                style={{ fontSize: bodySize }}
            >
                Lorem ipsum dolor sit amet, consectetur adipiscing elit.
            </p>
        </div>
    );
}

export default function ExportPreview({
    bookTitle,
    format,
    trimSize,
    fontSize,
    includeChapterTitles,
    showPageNumbers,
    includeActBreaks,
    selectedChapterIds,
    orderedChapters,
    frontMatter,
    backMatter,
    acts,
}: ExportPreviewProps) {
    const { t } = useTranslation('export');

    const pages = usePreviewPages({
        frontMatter,
        backMatter,
        orderedChapters,
        selectedChapterIds,
        includeActBreaks,
        includeChapterTitles,
        acts,
        format,
        trimSize,
        fontSize,
    });

    // Scale preview sizes relative to the default 11pt
    const scale = fontSize / 11;
    const bodySize = `${(9 * scale).toFixed(1)}px`;
    const dropCapSize = `${(44 * scale).toFixed(0)}px`;
    const titleSize = `${(19 * scale).toFixed(0)}px`;
    const isMono = format === 'txt';

    const hasContent = pages.length > 0;

    return (
        <aside className="flex h-full w-[400px] shrink-0 flex-col items-center border-l border-border-subtle bg-neutral-bg">
            {/* Preview area */}
            <div className="flex w-full flex-1 flex-col items-center gap-3 overflow-y-auto px-7 py-6">
                {/* Header — aligned with page width */}
                <div
                    className="flex items-center justify-between"
                    style={{ width: PAGE_WIDTH }}
                >
                    <span className="text-[10px] font-semibold tracking-[0.01em] text-[#B5B5B5] uppercase dark:text-ink-faint">
                        {t('preview')}
                    </span>
                    <span className="text-[11px] text-[#B5B5B5] dark:text-ink-faint">
                        Classic
                    </span>
                </div>

                {!hasContent && (
                    <div className="flex flex-1 items-center justify-center">
                        <p className="text-center text-[12px] text-ink-muted">
                            {t('preview.empty')}
                        </p>
                    </div>
                )}

                {pages.map((page, i) => (
                    <PageShell key={page.key} isMono={isMono}>
                        {page.type === 'title-page' && (
                            <TitlePage
                                bookTitle={bookTitle}
                                titleSize={titleSize}
                            />
                        )}
                        {page.type === 'copyright' && (
                            <CopyrightPage bodySize={bodySize} />
                        )}
                        {page.type === 'toc' && (
                            <TocPage
                                chapterTitles={page.chapterTitles ?? []}
                                bodySize={bodySize}
                            />
                        )}
                        {page.type === 'dedication' && (
                            <DedicationPage bodySize={bodySize} />
                        )}
                        {page.type === 'act-break' && (
                            <ActBreakPage
                                actNumber={page.actNumber ?? 0}
                                actTitle={page.actTitle}
                            />
                        )}
                        {page.type === 'chapter' && (
                            <ChapterPage
                                page={page}
                                format={format}
                                showPageNumbers={showPageNumbers}
                                pageNumber={i + 1}
                                bodySize={bodySize}
                                titleSize={titleSize}
                                dropCapSize={dropCapSize}
                            />
                        )}
                        {(page.type === 'acknowledgments' ||
                            page.type === 'about-author' ||
                            page.type === 'also-by') && (
                            <BackMatterPage
                                type={page.type}
                                bodySize={bodySize}
                            />
                        )}
                    </PageShell>
                ))}
            </div>
        </aside>
    );
}
