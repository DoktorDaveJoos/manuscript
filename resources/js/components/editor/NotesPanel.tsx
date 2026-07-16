import {
    AlertCircle,
    Check,
    Columns3,
    NotebookPen,
    Rows3,
    Trash2,
} from 'lucide-react';
import MarkdownIt from 'markdown-it';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { updateNotes } from '@/actions/App/Http/Controllers/ChapterController';
import NotesSlashMenu from '@/components/editor/NotesSlashMenu';
import { Alert, AlertDescription } from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import Checkbox from '@/components/ui/Checkbox';
import Input from '@/components/ui/Input';
import Kbd from '@/components/ui/Kbd';
import PanelHeader from '@/components/ui/PanelHeader';
import { Spinner } from '@/components/ui/spinner';
import { getNoteBlockNavigation, shouldShowNotesEscapeHint } from '@/lib/notes';
import { cn, jsonFetchHeaders } from '@/lib/utils';

const md = new MarkdownIt({ linkify: true });
const DEFAULT_MAX_NOTES_LENGTH = 10_000;

type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';
type NotesError = 'notes.error.maxLength' | 'notes.error.saveFailed';
export type BlockType =
    | 'text'
    | 'todo'
    | 'bullet'
    | 'heading'
    | 'divider'
    | 'callout'
    | 'table';

type LineData = {
    id: number;
    type: BlockType;
    text: string;
    checked?: boolean;
    table?: string[][];
};

let nextLineId = 0;
function createLine(
    type: BlockType,
    text: string,
    checked?: boolean,
    table?: string[][],
): LineData {
    return { id: nextLineId++, type, text, checked, table };
}

function parseTableRow(raw: string): string[] {
    return raw
        .trim()
        .replace(/^\|/, '')
        .replace(/\|$/, '')
        .split('|')
        .map((cell) => cell.trim());
}

function isTableDivider(raw: string): boolean {
    return /^\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?$/.test(raw.trim());
}

function parseLine(raw: string): {
    type: BlockType;
    text: string;
    checked?: boolean;
} {
    if (/^\[x\] /i.test(raw))
        return { type: 'todo', text: raw.slice(4), checked: true };
    if (raw.startsWith('[ ] '))
        return { type: 'todo', text: raw.slice(4), checked: false };
    if (raw.startsWith('- ')) return { type: 'bullet', text: raw.slice(2) };
    if (raw.startsWith('## ')) return { type: 'heading', text: raw.slice(3) };
    if (raw === '---') return { type: 'divider', text: '' };
    if (raw.startsWith('> ')) return { type: 'callout', text: raw.slice(2) };
    return { type: 'text', text: raw };
}

function parseLines(text: string): LineData[] {
    if (!text) return [createLine('text', '')];

    const rawLines = text.split('\n');
    const lines: LineData[] = [];

    for (let index = 0; index < rawLines.length; index++) {
        const raw = rawLines[index];
        if (
            raw.trim().startsWith('|') &&
            index + 1 < rawLines.length &&
            isTableDivider(rawLines[index + 1])
        ) {
            const table = [parseTableRow(raw)];
            index += 2;

            while (
                index < rawLines.length &&
                rawLines[index].trim().startsWith('|')
            ) {
                table.push(parseTableRow(rawLines[index]));
                index++;
            }

            index--;
            lines.push(createLine('table', '', undefined, table));
            continue;
        }

        const p = parseLine(raw);
        lines.push(createLine(p.type, p.text, p.checked));
    }

    return lines.length > 0 ? lines : [createLine('text', '')];
}

function serializeLine(line: LineData): string {
    switch (line.type) {
        case 'todo':
            return `[${line.checked ? 'x' : ' '}] ${line.text}`;
        case 'bullet':
            return `- ${line.text}`;
        case 'heading':
            return `## ${line.text}`;
        case 'divider':
            return '---';
        case 'callout':
            return `> ${line.text}`;
        case 'table': {
            const table = line.table ?? [
                ['', ''],
                ['', ''],
            ];
            const columns = Math.max(2, table[0]?.length ?? 0);
            const normalized = table.map((row) =>
                Array.from({ length: columns }, (_, index) => row[index] ?? ''),
            );
            const [header, ...rows] = normalized;
            return [
                `| ${header.join(' | ')} |`,
                `| ${header.map(() => '---').join(' | ')} |`,
                ...rows.map((row) => `| ${row.join(' | ')} |`),
            ].join('\n');
        }
        default:
            return line.text;
    }
}

function serializeLines(lines: LineData[]): string {
    return lines.map(serializeLine).join('\n');
}

function maxLineTextLength(
    lines: LineData[],
    index: number,
    maxLength: number,
): number {
    const line = lines[index];
    if (!line) return maxLength;

    const nonTextLength = serializeLines(lines).length - line.text.length;

    return Math.max(0, maxLength - nonTextLength);
}

function detectConversion(
    text: string,
): { type: BlockType; remaining: string; checked?: boolean } | null {
    if (text.startsWith('[ ] '))
        return { type: 'todo', remaining: text.slice(4), checked: false };
    if (text.startsWith('- '))
        return { type: 'bullet', remaining: text.slice(2) };
    if (text.startsWith('## '))
        return { type: 'heading', remaining: text.slice(3) };
    if (text.startsWith('> '))
        return { type: 'callout', remaining: text.slice(2) };
    return null;
}

export default function NotesPanel({
    bookId,
    chapterId,
    initialNotes,
    initialVersion,
    saveUrl,
    title,
    placeholder,
    variant = 'panel',
    maxLength = DEFAULT_MAX_NOTES_LENGTH,
    onNotesChange,
    onClose,
}: {
    bookId: number;
    chapterId?: number;
    initialNotes: string | null;
    initialVersion?: number;
    saveUrl?: string;
    title?: string;
    placeholder?: string;
    variant?: 'panel' | 'page';
    maxLength?: number;
    onNotesChange?: (notes: string | null) => void;
    onClose?: () => void;
}) {
    const { t } = useTranslation('editor');
    const initialLines = useRef(parseLines(initialNotes ?? ''));
    const [lines, setLines] = useState<LineData[]>(initialLines.current);
    const [activeIndex, setActiveIndex] = useState(
        initialLines.current.length - 1,
    );
    const [saveStatus, setSaveStatus] = useState<SaveStatus>('idle');
    const [saveError, setSaveError] = useState<NotesError | null>(null);
    const inputRef = useRef<HTMLTextAreaElement>(null);
    const abortRef = useRef<AbortController | null>(null);
    const inFlightRef = useRef(false);
    const pendingRef = useRef<'dirty' | null>(null);
    const savedTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const saveDelayRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const versionRef = useRef(initialVersion);
    const [slashMenu, setSlashMenu] = useState<{
        top: number;
        left: number;
        flip: boolean;
    } | null>(null);
    const slashMenuRef = useRef(slashMenu);
    useEffect(() => {
        slashMenuRef.current = slashMenu;
    }, [slashMenu]);
    const slashDismissedRef = useRef(false);
    const cursorPosRef = useRef<number | null>(null);
    const linesRef = useRef(lines);
    useEffect(() => {
        linesRef.current = lines;
    }, [lines]);
    const savedNotesRef = useRef(initialNotes);
    const resolvedSaveUrl =
        saveUrl ??
        (chapterId !== undefined
            ? updateNotes.url({ book: bookId, chapter: chapterId })
            : null);

    // Sync local state when initialNotes prop changes externally
    // (e.g., pane regains focus and softRefresh returns fresh data).
    // Guarded against clobbering in-flight user edits.
    useEffect(() => {
        if (pendingRef.current === 'dirty') return;
        const incoming = initialNotes ?? '';
        const current = savedNotesRef.current ?? '';
        if (incoming === current) return;
        savedNotesRef.current = initialNotes;
        const nextLines = parseLines(incoming);
        linesRef.current = nextLines;
        setLines(nextLines);
        setActiveIndex(nextLines.length - 1);
        setSlashMenu(null);
        setSaveError(null);
        setSaveStatus('idle');
    }, [initialNotes]);

    useEffect(() => {
        requestAnimationFrame(() => {
            const input = inputRef.current;
            if (!input) return;
            input.focus();
            if (cursorPosRef.current !== null) {
                input.setSelectionRange(
                    cursorPosRef.current,
                    cursorPosRef.current,
                );
                cursorPosRef.current = null;
            }
        });
    }, [activeIndex]);

    // Auto-grow the active block's textarea to fit its content so long lines
    // wrap to the panel width instead of scrolling horizontally.
    useEffect(() => {
        const el = inputRef.current;
        if (!el) return;
        el.style.height = 'auto';
        el.style.height = `${el.scrollHeight}px`;
    }, [activeIndex, lines]);

    const applyLines = useCallback(
        (next: LineData[]): boolean => {
            if (serializeLines(next).length > maxLength) {
                setSaveError('notes.error.maxLength');
                setSaveStatus('error');

                return false;
            }

            setSaveError(null);
            linesRef.current = next;
            setLines(next);

            return true;
        },
        [maxLength],
    );

    const flush = useCallback(async () => {
        if (pendingRef.current === null) return;
        if (!resolvedSaveUrl) return;
        if (initialVersion !== undefined && inFlightRef.current) return;

        pendingRef.current = null;
        const value = serializeLines(linesRef.current);

        if (value.length > maxLength) {
            pendingRef.current = 'dirty';
            setSaveError('notes.error.maxLength');
            setSaveStatus('error');

            return;
        }

        const controller = new AbortController();
        if (initialVersion === undefined) {
            abortRef.current?.abort();
            abortRef.current = controller;
        } else {
            inFlightRef.current = true;
        }

        if (savedTimerRef.current) clearTimeout(savedTimerRef.current);
        savedTimerRef.current = null;
        setSaveError(null);
        setSaveStatus('saving');
        let shouldFlushAgain = false;

        try {
            const payload: {
                notes: string | null;
                expected_version?: number;
            } = { notes: value || null };
            if (versionRef.current !== undefined) {
                payload.expected_version = versionRef.current;
            }

            const response = await fetch(resolvedSaveUrl, {
                method: 'PATCH',
                headers: jsonFetchHeaders(),
                body: JSON.stringify(payload),
                signal:
                    initialVersion === undefined
                        ? controller.signal
                        : undefined,
            });
            if (response.ok) {
                const result = (await response.json()) as {
                    notes_version?: number;
                };
                if (result.notes_version !== undefined) {
                    versionRef.current = result.notes_version;
                }
                savedNotesRef.current = value || null;
                onNotesChange?.(value || null);
                setSaveError(null);
                setSaveStatus('saved');
                savedTimerRef.current = setTimeout(
                    () => setSaveStatus('idle'),
                    2000,
                );
                shouldFlushAgain = pendingRef.current === 'dirty';
            } else {
                pendingRef.current = 'dirty';
                setSaveError(
                    response.status === 422
                        ? 'notes.error.maxLength'
                        : 'notes.error.saveFailed',
                );
                setSaveStatus('error');
            }
        } catch (e) {
            if ((e as Error).name !== 'AbortError') {
                pendingRef.current = 'dirty';
                setSaveError('notes.error.saveFailed');
                setSaveStatus('error');
            }
        } finally {
            if (initialVersion !== undefined) {
                inFlightRef.current = false;
                if (shouldFlushAgain) {
                    window.setTimeout(() => flushRef.current(), 0);
                }
            }
        }
    }, [initialVersion, maxLength, onNotesChange, resolvedSaveUrl]);

    const flushRef = useRef(flush);
    useEffect(() => {
        flushRef.current = flush;
    }, [flush]);

    const save = useCallback(() => {
        pendingRef.current = 'dirty';
        if (initialVersion === undefined) {
            flushRef.current();
            return;
        }

        if (saveDelayRef.current) clearTimeout(saveDelayRef.current);
        saveDelayRef.current = setTimeout(() => flushRef.current(), 400);
    }, [initialVersion]);

    useEffect(() => {
        return () => {
            if (savedTimerRef.current) clearTimeout(savedTimerRef.current);
            if (saveDelayRef.current) clearTimeout(saveDelayRef.current);
            const value = serializeLines(linesRef.current);
            if (
                pendingRef.current !== null ||
                value !== (savedNotesRef.current ?? '')
            ) {
                if (value.length > maxLength) return;

                if (initialVersion !== undefined) {
                    pendingRef.current = 'dirty';
                    void flushRef.current();
                } else if (resolvedSaveUrl) {
                    pendingRef.current = null;
                    fetch(resolvedSaveUrl, {
                        method: 'PATCH',
                        headers: jsonFetchHeaders(),
                        body: JSON.stringify({ notes: value || null }),
                    })
                        .then((response) => {
                            if (response.ok) onNotesChange?.(value || null);
                        })
                        .catch(() => {});
                }
            }
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [initialVersion, maxLength, resolvedSaveUrl]);

    const updateLine = useCallback(
        (index: number, updates: Partial<LineData>) => {
            const next = linesRef.current.map((line, lineIndex) =>
                lineIndex === index ? { ...line, ...updates } : line,
            );

            if (applyLines(next)) save();
        },
        [applyLines, save],
    );

    const toggleCheck = useCallback(
        (index: number) => {
            const line = linesRef.current[index];
            if (line.type === 'todo')
                updateLine(index, { checked: !line.checked });
        },
        [updateLine],
    );

    const updateTableCell = useCallback(
        (
            lineIndex: number,
            rowIndex: number,
            columnIndex: number,
            value: string,
        ) => {
            const table = (linesRef.current[lineIndex].table ?? []).map(
                (row) => [...row],
            );
            table[rowIndex][columnIndex] = value;
            updateLine(lineIndex, { table });
        },
        [updateLine],
    );

    const addTableRow = useCallback(
        (lineIndex: number) => {
            const table = (linesRef.current[lineIndex].table ?? []).map(
                (row) => [...row],
            );
            const columnCount = Math.max(2, table[0]?.length ?? 0);
            table.push(Array.from({ length: columnCount }, () => ''));
            updateLine(lineIndex, { table });
        },
        [updateLine],
    );

    const addTableColumn = useCallback(
        (lineIndex: number) => {
            const table = (linesRef.current[lineIndex].table ?? []).map(
                (row) => [...row, ''],
            );
            updateLine(lineIndex, { table });
        },
        [updateLine],
    );

    const removeTable = useCallback(
        (lineIndex: number) => {
            const next = [...linesRef.current];
            next.splice(lineIndex, 1);
            if (next.length === 0) next.push(createLine('text', ''));

            if (applyLines(next)) {
                setActiveIndex(Math.max(0, lineIndex - 1));
                save();
            }
        },
        [applyLines, save],
    );

    const handleLineChange = useCallback(
        (e: React.ChangeEvent<HTMLTextAreaElement>) => {
            const value = e.target.value;
            const line = linesRef.current[activeIndex];

            // Detect block-type conversion for text blocks
            if (line.type === 'text') {
                const conv = detectConversion(value);
                if (conv) {
                    updateLine(activeIndex, {
                        type: conv.type,
                        text: conv.remaining,
                        checked: conv.checked,
                    });
                    requestAnimationFrame(() => {
                        const input = inputRef.current;
                        if (input)
                            input.setSelectionRange(
                                conv.remaining.length,
                                conv.remaining.length,
                            );
                    });
                    return;
                }
            }

            updateLine(activeIndex, { text: value });

            // Slash menu: open on "/" in text block, keep open while text starts with "/"
            if (value.startsWith('/') && line.type === 'text') {
                if (!slashMenuRef.current && !slashDismissedRef.current) {
                    const input = e.target;
                    const inputRect = input.getBoundingClientRect();
                    const panelEl = input.closest('[data-notes-panel]');
                    const panelRect = panelEl?.getBoundingClientRect();
                    if (panelRect) {
                        const lineTop = inputRect.top - panelRect.top;
                        const menuHeight = 250;
                        const flip =
                            lineTop + inputRect.height + menuHeight >
                            panelRect.height;
                        setSlashMenu({
                            top: flip ? lineTop : lineTop + inputRect.height,
                            left: 20,
                            flip,
                        });
                    }
                }
            } else {
                if (slashMenuRef.current) setSlashMenu(null);
                slashDismissedRef.current = false;
            }
        },
        [activeIndex, updateLine],
    );

    const handleKeyDown = useCallback(
        (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
            if (slashMenuRef.current) return;
            const input = e.currentTarget;
            const line = linesRef.current[activeIndex];
            const blockNavigation = getNoteBlockNavigation({
                key: e.key,
                activeIndex,
                blockCount: linesRef.current.length,
                selectionStart: input.selectionStart,
                selectionEnd: input.selectionEnd,
                valueLength: input.value.length,
                hasModifier: e.altKey || e.ctrlKey || e.metaKey || e.shiftKey,
            });

            if (e.key === 'Enter') {
                e.preventDefault();

                // '---' in text block → divider
                if (line.type === 'text' && line.text === '---') {
                    const next = [...linesRef.current];
                    next[activeIndex] = {
                        ...next[activeIndex],
                        type: 'divider',
                        text: '',
                    };
                    next.splice(activeIndex + 1, 0, createLine('text', ''));
                    if (!applyLines(next)) return;

                    cursorPosRef.current = 0;
                    setActiveIndex(activeIndex + 1);
                    save();
                    return;
                }

                // Empty continuable block → revert to text
                if (
                    !line.text &&
                    (line.type === 'todo' ||
                        line.type === 'bullet' ||
                        line.type === 'callout')
                ) {
                    updateLine(activeIndex, {
                        type: 'text',
                        checked: undefined,
                    });
                    return;
                }

                // Divider → new text line below
                if (line.type === 'divider') {
                    const next = [...linesRef.current];
                    next.splice(activeIndex + 1, 0, createLine('text', ''));
                    if (!applyLines(next)) return;

                    cursorPosRef.current = 0;
                    setActiveIndex(activeIndex + 1);
                    save();
                    return;
                }

                // Split line — continue same type for todo/bullet/callout
                const pos = input.selectionStart ?? input.value.length;
                const before = input.value.substring(0, pos);
                const after = input.value.substring(pos);
                const continueType =
                    line.type === 'todo' ||
                    line.type === 'bullet' ||
                    line.type === 'callout'
                        ? line.type
                        : 'text';

                const next = [...linesRef.current];
                next[activeIndex] = { ...next[activeIndex], text: before };
                next.splice(
                    activeIndex + 1,
                    0,
                    createLine(
                        continueType,
                        after,
                        continueType === 'todo' ? false : undefined,
                    ),
                );
                if (!applyLines(next)) return;

                cursorPosRef.current = 0;
                setActiveIndex(activeIndex + 1);
                save();
            } else if (e.key === 'Backspace') {
                // Divider active → delete it
                if (line.type === 'divider') {
                    e.preventDefault();
                    const next = [...linesRef.current];
                    next.splice(activeIndex, 1);
                    if (next.length === 0) next.push(createLine('text', ''));
                    if (!applyLines(next)) return;

                    const newIdx = Math.max(0, activeIndex - 1);
                    cursorPosRef.current = next[newIdx]?.text.length ?? 0;
                    setActiveIndex(newIdx);
                    save();
                    return;
                }

                if (input.selectionStart === 0 && input.selectionEnd === 0) {
                    // Non-text block at start → revert to text
                    if (line.type !== 'text') {
                        e.preventDefault();
                        updateLine(activeIndex, {
                            type: 'text',
                            checked: undefined,
                        });
                        return;
                    }
                    // Merge with previous line
                    if (activeIndex > 0) {
                        e.preventDefault();
                        const prev = linesRef.current[activeIndex - 1];
                        if (prev.type === 'divider' || prev.type === 'table') {
                            const next = [...linesRef.current];
                            next.splice(activeIndex - 1, 1);
                            if (!applyLines(next)) return;

                            setActiveIndex(activeIndex - 1);
                            cursorPosRef.current = 0;
                        } else {
                            const prevText = prev.text;
                            const next = [...linesRef.current];
                            next[activeIndex - 1] = {
                                ...next[activeIndex - 1],
                                text: prevText + next[activeIndex].text,
                            };
                            next.splice(activeIndex, 1);
                            if (!applyLines(next)) return;

                            cursorPosRef.current = prevText.length;
                            setActiveIndex(activeIndex - 1);
                        }
                        save();
                    }
                }
            } else if (blockNavigation === 'previous') {
                e.preventDefault();
                cursorPosRef.current =
                    linesRef.current[activeIndex - 1].text.length;
                setActiveIndex(activeIndex - 1);
            } else if (blockNavigation === 'next') {
                e.preventDefault();
                cursorPosRef.current = 0;
                setActiveIndex(activeIndex + 1);
            }
        },
        [activeIndex, applyLines, save, updateLine],
    );

    const handleSlashSelect = useCallback(
        (blockType: BlockType) => {
            if (blockType === 'divider' || blockType === 'table') {
                const next = [...linesRef.current];
                next[activeIndex] = {
                    ...next[activeIndex],
                    type: blockType,
                    text: '',
                    table:
                        blockType === 'table'
                            ? [
                                  [
                                      t('notes.table.column', { number: 1 }),
                                      t('notes.table.column', { number: 2 }),
                                  ],
                                  ['', ''],
                              ]
                            : undefined,
                };
                next.splice(activeIndex + 1, 0, createLine('text', ''));
                if (!applyLines(next)) return;

                cursorPosRef.current = 0;
                setActiveIndex(activeIndex + 1);
                save();
            } else {
                updateLine(activeIndex, {
                    type: blockType,
                    text: '',
                    checked: blockType === 'todo' ? false : undefined,
                });
            }
            setSlashMenu(null);
            requestAnimationFrame(() => inputRef.current?.focus());
        },
        [activeIndex, applyLines, updateLine, save, t],
    );

    const handleSlashClose = useCallback(() => {
        setSlashMenu(null);
        slashDismissedRef.current = true;
        inputRef.current?.focus();
    }, []);

    const handlePaste = useCallback(
        (e: React.ClipboardEvent<HTMLTextAreaElement>) => {
            const text = e.clipboardData.getData('text');
            if (!text.includes('\n')) return;

            e.preventDefault();
            const input = e.currentTarget;
            const pos = input.selectionStart ?? 0;
            const end = input.selectionEnd ?? pos;
            const current = input.value;
            const before = current.substring(0, pos);
            const after = current.substring(end);
            const pastedLines = text.split('\n');
            const currentLine = linesRef.current[activeIndex];

            const newLines: LineData[] = [];
            newLines.push(
                createLine(
                    currentLine.type,
                    before + pastedLines[0],
                    currentLine.checked,
                ),
            );
            for (let i = 1; i < pastedLines.length; i++) {
                const raw =
                    i === pastedLines.length - 1
                        ? pastedLines[i] + after
                        : pastedLines[i];
                const p = parseLine(raw);
                newLines.push(createLine(p.type, p.text, p.checked));
            }

            const next = [...linesRef.current];
            next.splice(activeIndex, 1, ...newLines);
            if (!applyLines(next)) return;

            const newActiveIndex = activeIndex + newLines.length - 1;
            cursorPosRef.current = newLines[newLines.length - 1].text.length;
            setActiveIndex(newActiveIndex);
            save();
        },
        [activeIndex, applyLines, save],
    );

    const handleClick = useCallback((index: number) => {
        cursorPosRef.current = linesRef.current[index].text.length;
        setActiveIndex(index);
    }, []);

    const handleCanvasClick = useCallback(
        (event: React.MouseEvent<HTMLDivElement>) => {
            if (event.target !== event.currentTarget) return;

            const lastIndex = linesRef.current.length - 1;
            const lastLine = linesRef.current[lastIndex];

            if (lastLine.type === 'table' || lastLine.type === 'divider') {
                const next = [...linesRef.current, createLine('text', '')];
                if (!applyLines(next)) return;

                cursorPosRef.current = 0;
                setActiveIndex(next.length - 1);
            } else {
                cursorPosRef.current = lastLine.text.length;
                setActiveIndex(lastIndex);
            }

            requestAnimationFrame(() => inputRef.current?.focus());
        },
        [applyLines],
    );

    const notesLength = serializeLines(lines).length;
    const visibleError =
        saveError ??
        (notesLength >= maxLength ? 'notes.error.maxLength' : null);

    return (
        <div
            data-notes-panel
            className={cn(
                'relative flex h-full shrink-0 flex-col',
                variant === 'panel'
                    ? 'border-l border-border bg-surface-sidebar'
                    : 'bg-surface-card',
            )}
        >
            <PanelHeader
                title={title ?? t('notes.title')}
                icon={<NotebookPen size={14} className="text-ink-muted" />}
                onClose={onClose}
                suffix={
                    <>
                        {saveStatus === 'saving' && (
                            <Spinner
                                data-notes-save-status="saving"
                                aria-label={t('notes.saving')}
                                className="size-3.5 text-ink-faint"
                            />
                        )}
                        {saveStatus === 'saved' && (
                            <Check
                                role="img"
                                data-notes-save-status="saved"
                                aria-label={t('notes.saved')}
                                className="size-3.5 text-ink-faint"
                            />
                        )}
                        {saveStatus === 'error' && (
                            <AlertCircle
                                role="img"
                                data-notes-save-status="error"
                                aria-label={t(
                                    saveError ?? 'notes.error.saveFailed',
                                )}
                                className="size-3.5 text-delete"
                            />
                        )}
                        <Kbd keys="/" />
                        {shouldShowNotesEscapeHint(chapterId) && (
                            <Kbd keys="Esc" />
                        )}
                    </>
                }
            />
            {visibleError && (
                <Alert
                    data-notes-error
                    variant="destructive"
                    className="mx-5 mt-4 p-3"
                >
                    <AlertDescription className="text-delete">
                        {t(visibleError)}
                    </AlertDescription>
                </Alert>
            )}
            <div
                data-notes-canvas
                onClick={handleCanvasClick}
                className="flex min-h-0 flex-1 cursor-text flex-col overflow-y-auto p-5"
            >
                {lines.map((line, i) => {
                    const isActive = i === activeIndex;

                    if (line.type === 'table') {
                        const table = line.table ?? [
                            ['', ''],
                            ['', ''],
                        ];

                        return (
                            <div
                                key={line.id}
                                data-notes-table
                                className="flex flex-col gap-2 py-3"
                            >
                                <div className="overflow-hidden rounded-lg border border-border">
                                    <table className="w-full border-collapse">
                                        <thead className="bg-neutral-bg">
                                            <tr>
                                                {table[0].map(
                                                    (cell, columnIndex) => (
                                                        <th
                                                            key={columnIndex}
                                                            className="border-r border-border text-left last:border-r-0"
                                                        >
                                                            <Input
                                                                variant="table"
                                                                className={cn(
                                                                    columnIndex ===
                                                                        0 &&
                                                                        'rounded-tl-lg',
                                                                    columnIndex ===
                                                                        table[0]
                                                                            .length -
                                                                            1 &&
                                                                        'rounded-tr-lg',
                                                                )}
                                                                aria-label={t(
                                                                    'notes.table.headerLabel',
                                                                    {
                                                                        number:
                                                                            columnIndex +
                                                                            1,
                                                                    },
                                                                )}
                                                                value={cell}
                                                                onFocus={() =>
                                                                    setActiveIndex(
                                                                        i,
                                                                    )
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateTableCell(
                                                                        i,
                                                                        0,
                                                                        columnIndex,
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
                                                        </th>
                                                    ),
                                                )}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {table
                                                .slice(1)
                                                .map((row, rowIndex) => (
                                                    <tr
                                                        key={rowIndex}
                                                        className="border-t border-border"
                                                    >
                                                        {row.map(
                                                            (
                                                                cell,
                                                                columnIndex,
                                                            ) => (
                                                                <td
                                                                    key={
                                                                        columnIndex
                                                                    }
                                                                    className="border-r border-border last:border-r-0"
                                                                >
                                                                    <Input
                                                                        variant="table"
                                                                        className={cn(
                                                                            rowIndex ===
                                                                                table.length -
                                                                                    2 &&
                                                                                columnIndex ===
                                                                                    0 &&
                                                                                'rounded-bl-lg',
                                                                            rowIndex ===
                                                                                table.length -
                                                                                    2 &&
                                                                                columnIndex ===
                                                                                    row.length -
                                                                                        1 &&
                                                                                'rounded-br-lg',
                                                                        )}
                                                                        aria-label={t(
                                                                            'notes.table.cellLabel',
                                                                            {
                                                                                row:
                                                                                    rowIndex +
                                                                                    1,
                                                                                column:
                                                                                    columnIndex +
                                                                                    1,
                                                                            },
                                                                        )}
                                                                        value={
                                                                            cell
                                                                        }
                                                                        onFocus={() =>
                                                                            setActiveIndex(
                                                                                i,
                                                                            )
                                                                        }
                                                                        onChange={(
                                                                            event,
                                                                        ) =>
                                                                            updateTableCell(
                                                                                i,
                                                                                rowIndex +
                                                                                    1,
                                                                                columnIndex,
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                            )
                                                                        }
                                                                    />
                                                                </td>
                                                            ),
                                                        )}
                                                    </tr>
                                                ))}
                                        </tbody>
                                    </table>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        onClick={() => addTableRow(i)}
                                    >
                                        <Rows3
                                            size={14}
                                            data-icon="inline-start"
                                        />
                                        {t('notes.table.addRow')}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        onClick={() => addTableColumn(i)}
                                    >
                                        <Columns3
                                            size={14}
                                            data-icon="inline-start"
                                        />
                                        {t('notes.table.addColumn')}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="dangerSecondary"
                                        size="sm"
                                        data-notes-remove-table
                                        onClick={() => removeTable(i)}
                                    >
                                        <Trash2
                                            size={14}
                                            data-icon="inline-start"
                                        />
                                        {t('notes.table.remove')}
                                    </Button>
                                </div>
                            </div>
                        );
                    }

                    // --- Divider ---
                    if (line.type === 'divider') {
                        return (
                            <div
                                key={line.id}
                                className="cursor-text py-1.5"
                                onClick={() => handleClick(i)}
                            >
                                <hr
                                    className={`border-t ${isActive ? 'border-accent' : 'border-border'}`}
                                />
                                {isActive && (
                                    <textarea
                                        ref={inputRef}
                                        className="sr-only"
                                        onKeyDown={handleKeyDown}
                                        readOnly
                                    />
                                )}
                            </div>
                        );
                    }

                    // --- Block prefix elements ---
                    const checkbox = line.type === 'todo' && (
                        <Checkbox
                            checked={Boolean(line.checked)}
                            onChange={() => toggleCheck(i)}
                            className="mt-1"
                        />
                    );

                    const bullet = line.type === 'bullet' && (
                        <span className="mt-[6px] h-1 w-1 shrink-0 rounded-full bg-ink-muted" />
                    );

                    const textClass = [
                        'min-w-0 flex-1 leading-5',
                        line.type === 'heading'
                            ? 'font-semibold text-sm'
                            : 'text-[13px]',
                        line.type === 'todo' &&
                            line.checked &&
                            'text-ink-faint line-through',
                    ]
                        .filter(Boolean)
                        .join(' ');

                    const wrapClass = `flex items-start gap-1.5 ${line.type === 'callout' ? 'border-l-2 border-accent pl-2' : ''}`;

                    // --- Active line ---
                    if (isActive) {
                        return (
                            <div key={line.id} className={wrapClass}>
                                {checkbox}
                                {bullet}
                                <textarea
                                    ref={inputRef}
                                    data-notes-input
                                    rows={1}
                                    maxLength={maxLineTextLength(
                                        lines,
                                        i,
                                        maxLength,
                                    )}
                                    value={line.text}
                                    onChange={handleLineChange}
                                    onKeyDown={handleKeyDown}
                                    onPaste={handlePaste}
                                    placeholder={
                                        lines.length === 1 &&
                                        i === 0 &&
                                        line.type === 'text'
                                            ? (placeholder ??
                                              t('notes.placeholder'))
                                            : undefined
                                    }
                                    aria-invalid={Boolean(visibleError)}
                                    className={`${textClass} block w-full resize-none overflow-hidden border-0 bg-transparent p-0 font-sans text-ink placeholder:text-ink-faint focus:ring-0 focus:outline-none`}
                                />
                            </div>
                        );
                    }

                    // --- Inactive line ---
                    const html = line.text ? md.renderInline(line.text) : '';
                    return (
                        <div
                            key={line.id}
                            onClick={() => handleClick(i)}
                            className={`${wrapClass} cursor-text ${!line.text ? 'h-5' : ''}`}
                        >
                            {checkbox}
                            {bullet}
                            {html ? (
                                <span
                                    className={`${textClass} notes-line-md`}
                                    dangerouslySetInnerHTML={{ __html: html }}
                                />
                            ) : (
                                <span className={textClass} />
                            )}
                        </div>
                    );
                })}
            </div>
            {slashMenu && (
                <NotesSlashMenu
                    position={slashMenu}
                    query={lines[activeIndex]?.text.slice(1) ?? ''}
                    onSelect={handleSlashSelect}
                    onClose={handleSlashClose}
                />
            )}
        </div>
    );
}
