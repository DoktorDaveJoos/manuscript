import { useMemo } from 'react';
import type { Format } from '@/components/export/ExportSettings';
import { TRIM_RATIOS } from '@/components/export/trim-sizes';
import type { ActRef, ChapterRow, MatterItem } from '@/components/export/types';

export type PreviewPageType =
    | 'title-page'
    | 'copyright'
    | 'toc'
    | 'dedication'
    | 'act-break'
    | 'chapter'
    | 'acknowledgments'
    | 'about-author'
    | 'also-by';

export type PreviewPage = {
    type: PreviewPageType;
    key: string;
    title?: string;
    paragraphs?: string[];
    actNumber?: number;
    actTitle?: string;
    showTitle?: boolean;
    isFirst?: boolean;
    chapterTitles?: string[];
    chapterIndex?: number;
    pageInChapter?: number;
};

/** Strip HTML tags and decode common entities for plain-text preview. */
function stripHtml(html: string): string {
    return html
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<\/p>\s*<p[^>]*>/gi, '\n\n')
        .replace(/<[^>]+>/g, '')
        .replace(/&nbsp;/g, ' ')
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"')
        .replace(/&#39;/g, "'")
        .replace(/&ldquo;/g, '\u201C')
        .replace(/&rdquo;/g, '\u201D')
        .replace(/&lsquo;/g, '\u2018')
        .replace(/&rsquo;/g, '\u2019')
        .replace(/&mdash;/g, '\u2014')
        .replace(/&ndash;/g, '\u2013')
        .replace(/&hellip;/g, '\u2026');
}

/** Split content into paragraphs, stripping HTML. */
function contentToParagraphs(content: string | undefined): string[] {
    if (!content) return [];
    const text = stripHtml(content);
    return text
        .split(/\n{2,}/)
        .map((p) => p.trim())
        .filter(Boolean);
}

const PAGE_WIDTH = 220;
const TEXT_WIDTH = 180; // PAGE_WIDTH minus px-5 padding (20px each side)
const PADDING_TOP = 24; // pt-6
const PADDING_BOTTOM = 16; // pb-4
const RUNNING_HEADER_HEIGHT = 20;
const CHAPTER_TITLE_HEIGHT = 44;
const PAGE_NUMBER_HEIGHT = 15;

function getPageTextHeight(format: Format, trimSize: string): number {
    let ratio: number;
    if (format === 'epub') ratio = 3 / 4;
    else if (format === 'docx' || format === 'txt') ratio = 216 / 279;
    else ratio = TRIM_RATIOS[trimSize] ?? 152 / 229;

    const pageHeight = PAGE_WIDTH / ratio;
    return pageHeight - PADDING_TOP - PADDING_BOTTOM;
}

function estimateParaLines(text: string, charsPerLine: number): number {
    return Math.max(1, Math.ceil(text.length / charsPerLine));
}

function splitParagraphsIntoPages(
    paragraphs: string[],
    charsPerLine: number,
    firstPageMaxLines: number,
    continuationMaxLines: number,
): string[][] {
    if (paragraphs.length === 0) return [[]];

    const pages: string[][] = [];
    let current: string[] = [];
    let linesUsed = 0;
    let maxLines = firstPageMaxLines;

    for (const para of paragraphs) {
        const paraLines = estimateParaLines(para, charsPerLine);
        const spacing = current.length > 0 ? 1 : 0;

        if (current.length > 0 && linesUsed + spacing + paraLines > maxLines) {
            pages.push(current);
            current = [para];
            linesUsed = paraLines;
            maxLines = continuationMaxLines;
        } else {
            current.push(para);
            linesUsed += spacing + paraLines;
        }
    }

    if (current.length > 0) {
        pages.push(current);
    }

    return pages;
}

export function usePreviewPages({
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
}: {
    frontMatter: MatterItem[];
    backMatter: MatterItem[];
    orderedChapters: ChapterRow[];
    selectedChapterIds: Set<number>;
    includeActBreaks: boolean;
    includeChapterTitles: boolean;
    acts: ActRef[];
    format: Format;
    trimSize: string;
    fontSize: number;
}): PreviewPage[] {
    return useMemo(() => {
        const pages: PreviewPage[] = [];
        const actsById = new Map(acts.map((a) => [a.id, a]));

        const selectedChapters = orderedChapters.filter((ch) =>
            selectedChapterIds.has(ch.id),
        );

        // Front matter
        for (const item of frontMatter) {
            if (!item.checked) continue;
            const page: PreviewPage = {
                type: item.id as PreviewPageType,
                key: `fm-${item.id}`,
            };
            if (item.id === 'toc') {
                page.chapterTitles = selectedChapters.map((ch) => ch.title);
            }
            pages.push(page);
        }

        // Compute pagination parameters
        const scale = fontSize / 11;
        const bodySize = 7 * scale;
        const lineHeight = bodySize * 1.75;
        const charWidth = bodySize * 0.45;
        const charsPerLine = Math.max(1, Math.floor(TEXT_WIDTH / charWidth));
        const baseTextHeight = getPageTextHeight(format, trimSize);

        const hasRunningHeader = format === 'pdf';
        const hasPageNumbers = format === 'pdf';

        let firstPageHeight = baseTextHeight;
        let contPageHeight = baseTextHeight;

        if (hasRunningHeader) {
            firstPageHeight -= RUNNING_HEADER_HEIGHT;
            contPageHeight -= RUNNING_HEADER_HEIGHT;
        }
        if (includeChapterTitles) {
            firstPageHeight -= CHAPTER_TITLE_HEIGHT;
        }
        if (hasPageNumbers) {
            firstPageHeight -= PAGE_NUMBER_HEIGHT;
            contPageHeight -= PAGE_NUMBER_HEIGHT;
        }

        const firstPageMaxLines = Math.max(
            3,
            Math.floor(firstPageHeight / lineHeight),
        );
        const contPageMaxLines = Math.max(
            3,
            Math.floor(contPageHeight / lineHeight),
        );

        // All chapters
        let prevActId: number | null | undefined = undefined;

        for (let i = 0; i < selectedChapters.length; i++) {
            const ch = selectedChapters[i];

            // Act break
            if (
                includeActBreaks &&
                ch.act_id != null &&
                ch.act_id !== prevActId
            ) {
                const act = actsById.get(ch.act_id);
                if (act) {
                    pages.push({
                        type: 'act-break',
                        key: `act-${act.id}`,
                        actNumber: act.number,
                        actTitle: act.title ?? undefined,
                    });
                }
            }
            prevActId = ch.act_id;

            const paragraphs = contentToParagraphs(ch.content ?? undefined);
            const pageGroups = splitParagraphsIntoPages(
                paragraphs,
                charsPerLine,
                firstPageMaxLines,
                contPageMaxLines,
            );

            for (let g = 0; g < pageGroups.length; g++) {
                pages.push({
                    type: 'chapter',
                    key: `ch-${ch.id}-p${g}`,
                    title: ch.title,
                    paragraphs: pageGroups[g],
                    showTitle: includeChapterTitles && g === 0,
                    isFirst: i === 0 && g === 0,
                    chapterIndex: i,
                    pageInChapter: g,
                });
            }
        }

        // Back matter
        for (const item of backMatter) {
            if (!item.checked) continue;
            pages.push({
                type: item.id as PreviewPageType,
                key: `bm-${item.id}`,
            });
        }

        return pages;
    }, [
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
    ]);
}
