import { useMemo } from 'react';
import type { Format } from '@/components/export/ExportSettings';
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

export const SCENE_BREAK_MARKER = '***';
/** Prefix on paragraphs that are continuations split across pages. */
export const CONTINUATION_PREFIX = '\x01';

/** Strip HTML tags and decode common entities for plain-text preview. */
function stripHtml(html: string): string {
    return html
        .replace(/<hr\s*\/?>/gi, `\n\n${SCENE_BREAK_MARKER}\n\n`)
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

// Calculation constants — must stay in sync with PageShell in ExportPreview.tsx
export const PAGE_WIDTH = 340;
const PADDING_TOP = 40; // pt-10
const PADDING_BOTTOM = 32; // pb-8
const PADDING_LEFT = 40; // pl-10
const PADDING_RIGHT = 36; // pr-[36px]
const TEXT_WIDTH = PAGE_WIDTH - PADDING_LEFT - PADDING_RIGHT; // 264
const RUNNING_HEADER_HEIGHT = 24; // text-[7px] (~10px) + mb-3 (12px) ≈ 22, rounded up
const CHAPTER_TITLE_HEIGHT = 86; // pt-6 (24) + mb-1 (4) + label (~10) + title (~23) + pb-5 (20) ≈ 81, +5 safety
const PAGE_NUMBER_HEIGHT = 22; // mt-2 (8px) + text-[8px] (~12px) ≈ 20, rounded up

const CHAR_WIDTH_RATIO = 0.55; // average char width as fraction of font size (serif at preview scale)
const MIN_WIDOWS = 2;
const MIN_ORPHANS = 2;

/** Format-specific line-height multipliers matching actual export output.
 *  Keep in sync with PdfExporter.php (1.5) and EpubExporter.php (1.6). */
export function getLineHeightMultiplier(format: Format): number {
    switch (format) {
        case 'pdf':
            return 1.5;
        case 'epub':
            return 1.6;
        default:
            return 1.75;
    }
}

/** Parse a trim size string like "6x9" into width/height numbers. */
export function parseTrimSize(trimSize: string): {
    width: number;
    height: number;
} {
    const parts = trimSize.split('x');
    const w = parseFloat(parts[0]);
    const h = parseFloat(parts[1]);
    if (isNaN(w) || isNaN(h) || w <= 0 || h <= 0) {
        return { width: 6, height: 9 }; // fallback
    }
    return { width: w, height: h };
}

/** Compute page render height from trim size ratio, keeping PAGE_WIDTH fixed. */
export function computePageHeight(trimSize: string): number {
    const { width, height } = parseTrimSize(trimSize);
    return Math.round(PAGE_WIDTH * (height / width));
}

/** Estimate line count by simulating word-wrap (words can't split mid-line). */
function estimateParaLines(text: string, charsPerLine: number): number {
    const words = text.split(/\s+/).filter(Boolean);
    if (words.length === 0) return 1;

    let lines = 1;
    let lineLen = 0;

    for (const word of words) {
        const needed = lineLen === 0 ? word.length : word.length + 1;
        if (lineLen > 0 && lineLen + needed > charsPerLine) {
            lines++;
            lineLen = word.length;
        } else {
            lineLen += needed;
        }
    }

    return lines;
}

/**
 * Find the last word-boundary space before targetPos. Returns -1 if no good
 * break exists. Rejects breaks in the first 30% to avoid leaving a very short
 * fragment on the page (looks worse than pushing the whole paragraph).
 */
function findWordBreak(text: string, targetPos: number): number {
    if (targetPos >= text.length) return -1;
    const MIN_FRAGMENT_RATIO = 0.3;
    const lastSpace = text.lastIndexOf(' ', targetPos);
    return lastSpace > targetPos * MIN_FRAGMENT_RATIO ? lastSpace : -1;
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
        const isBreak = para === SCENE_BREAK_MARKER;
        const paraLines = isBreak ? 3 : estimateParaLines(para, charsPerLine);

        if (current.length > 0 && linesUsed + paraLines > maxLines) {
            // Try to split a long text paragraph across this page and the next
            const availableLines = maxLines - linesUsed;
            if (!isBreak && availableLines >= MIN_ORPHANS) {
                const breakCharPos = availableLines * charsPerLine;
                const splitIdx = findWordBreak(para, breakCharPos);
                if (splitIdx > 0) {
                    const part1 = para.slice(0, splitIdx).trimEnd();
                    const part2 = para.slice(splitIdx).trimStart();
                    const part2Lines = estimateParaLines(part2, charsPerLine);

                    // Widow prevention: if part2 would be fewer than MIN_WIDOWS
                    // lines on the next page, reject the split
                    if (part1 && part2 && part2Lines >= MIN_WIDOWS) {
                        current.push(part1);
                        pages.push(current);
                        current = [CONTINUATION_PREFIX + part2];
                        linesUsed = part2Lines;
                        maxLines = continuationMaxLines;
                        continue;
                    }
                }
            }

            // Orphan prevention: if current page would end with only 1 line
            // from the last paragraph, pull it to the next page too
            if (
                !isBreak &&
                current.length > 0 &&
                availableLines < MIN_ORPHANS
            ) {
                const lastPara = current[current.length - 1];
                if (
                    lastPara &&
                    lastPara !== SCENE_BREAK_MARKER &&
                    estimateParaLines(lastPara, charsPerLine) === 1 &&
                    current.length > 1
                ) {
                    current.pop();
                    pages.push(current);
                    current = [lastPara, para];
                    linesUsed =
                        estimateParaLines(lastPara, charsPerLine) + paraLines;
                    maxLines = continuationMaxLines;
                    continue;
                }
            }

            // Cannot split — push whole paragraph to next page
            pages.push(current);
            current = [para];
            linesUsed = paraLines;
            maxLines = continuationMaxLines;
        } else {
            current.push(para);
            linesUsed += paraLines;
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
    showPageNumbers,
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
    showPageNumbers: boolean;
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

        // Compute pagination parameters — must match rendered PageShell sizing
        const pageHeight = computePageHeight(trimSize);
        const baseTextHeight = pageHeight - PADDING_TOP - PADDING_BOTTOM;
        const scale = fontSize / 11;
        const bodySize = 9 * scale; // matches ExportPreview render
        const lineHeightMultiplier = getLineHeightMultiplier(format);
        const lineHeight = bodySize * lineHeightMultiplier;
        const charWidth = bodySize * CHAR_WIDTH_RATIO;
        const charsPerLine = Math.max(1, Math.floor(TEXT_WIDTH / charWidth));

        const hasRunningHeader = format === 'pdf';

        let firstPageHeight = baseTextHeight;
        let contPageHeight = baseTextHeight;

        if (hasRunningHeader) {
            // Running header is suppressed on chapter openers
            contPageHeight -= RUNNING_HEADER_HEIGHT;
        }
        if (includeChapterTitles) {
            firstPageHeight -= CHAPTER_TITLE_HEIGHT;
        }
        if (showPageNumbers) {
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
        showPageNumbers,
        acts,
        format,
        trimSize,
        fontSize,
    ]);
}
