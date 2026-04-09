import { NotebookPen } from 'lucide-react';
import MarkdownIt from 'markdown-it';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { updateNotes } from '@/actions/App/Http/Controllers/ChapterController';
import NotesSlashMenu from '@/components/editor/NotesSlashMenu';
import Kbd from '@/components/ui/Kbd';
import PanelHeader from '@/components/ui/PanelHeader';
import { jsonFetchHeaders } from '@/lib/utils';

const md = new MarkdownIt({ linkify: true });

type SaveStatus = 'idle' | 'saving' | 'saved';
export type BlockType =
    | 'text'
    | 'todo'
    | 'bullet'
    | 'heading'
    | 'divider'
    | 'callout';

type LineData = {
    id: number;
    type: BlockType;
    text: string;
    checked?: boolean;
};

let nextLineId = 0;
function createLine(
    type: BlockType,
    text: string,
    checked?: boolean,
): LineData {
    return { id: nextLineId++, type, text, checked };
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
    return text.split('\n').map((raw) => {
        const p = parseLine(raw);
        return createLine(p.type, p.text, p.checked);
    });
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
        default:
            return line.text;
    }
}

function serializeLines(lines: LineData[]): string {
    return lines.map(serializeLine).join('\n');
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
    onNotesChange,
    onClose,
}: {
    bookId: number;
    chapterId: number;
    initialNotes: string | null;
    onNotesChange?: (notes: string | null) => void;
    onClose: () => void;
}) {
    const { t } = useTranslation('editor');
    const [lines, setLines] = useState<LineData[]>(() =>
        parseLines(initialNotes ?? ''),
    );
    const [activeIndex, setActiveIndex] = useState(() => {
        const initial = initialNotes ?? '';
        return initial ? initial.split('\n').length - 1 : 0;
    });
    const [saveStatus, setSaveStatus] = useState<SaveStatus>('idle');
    const inputRef = useRef<HTMLInputElement>(null);
    const abortRef = useRef<AbortController | null>(null);
    const pendingRef = useRef<'dirty' | null>(null);
    const savedTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
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
        setLines(nextLines);
        setActiveIndex(nextLines.length - 1);
        setSlashMenu(null);
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

    const flush = useCallback(async () => {
        if (pendingRef.current === null) return;
        pendingRef.current = null;
        const value = serializeLines(linesRef.current);

        abortRef.current?.abort();
        const controller = new AbortController();
        abortRef.current = controller;

        setSaveStatus('saving');
        try {
            const response = await fetch(
                updateNotes.url({ book: bookId, chapter: chapterId }),
                {
                    method: 'PATCH',
                    headers: jsonFetchHeaders(),
                    body: JSON.stringify({ notes: value || null }),
                    signal: controller.signal,
                },
            );
            if (response.ok) {
                savedNotesRef.current = value || null;
                onNotesChange?.(value || null);
                setSaveStatus('saved');
                if (savedTimerRef.current) clearTimeout(savedTimerRef.current);
                savedTimerRef.current = setTimeout(
                    () => setSaveStatus('idle'),
                    2000,
                );
            } else {
                setSaveStatus('idle');
            }
        } catch (e) {
            if ((e as Error).name !== 'AbortError') {
                setSaveStatus('idle');
            }
        }
    }, [bookId, chapterId, onNotesChange]);

    const flushRef = useRef(flush);
    useEffect(() => {
        flushRef.current = flush;
    }, [flush]);

    const save = useCallback(() => {
        pendingRef.current = 'dirty';
        flushRef.current();
    }, []);

    useEffect(() => {
        return () => {
            if (savedTimerRef.current) clearTimeout(savedTimerRef.current);
            abortRef.current?.abort();
            const value = serializeLines(linesRef.current);
            if (
                pendingRef.current !== null ||
                value !== (savedNotesRef.current ?? '')
            ) {
                pendingRef.current = null;
                onNotesChange?.(value || null);
                fetch(updateNotes.url({ book: bookId, chapter: chapterId }), {
                    method: 'PATCH',
                    headers: jsonFetchHeaders(),
                    body: JSON.stringify({ notes: value || null }),
                }).catch(() => {});
            }
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [bookId, chapterId]);

    const updateLine = useCallback(
        (index: number, updates: Partial<LineData>) => {
            setLines((prev) => {
                const next = prev.map((l, i) =>
                    i === index ? { ...l, ...updates } : l,
                );
                linesRef.current = next;
                return next;
            });
            save();
        },
        [save],
    );

    const toggleCheck = useCallback(
        (index: number) => {
            const line = linesRef.current[index];
            if (line.type === 'todo')
                updateLine(index, { checked: !line.checked });
        },
        [updateLine],
    );

    const handleLineChange = useCallback(
        (e: React.ChangeEvent<HTMLInputElement>) => {
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
                        const menuHeight = 210;
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
        (e: React.KeyboardEvent<HTMLInputElement>) => {
            if (slashMenuRef.current) return;
            const input = e.currentTarget;
            const line = linesRef.current[activeIndex];

            if (e.key === 'Enter') {
                e.preventDefault();

                // '---' in text block → divider
                if (line.type === 'text' && line.text === '---') {
                    setLines((prev) => {
                        const next = [...prev];
                        next[activeIndex] = {
                            ...next[activeIndex],
                            type: 'divider',
                            text: '',
                        };
                        next.splice(activeIndex + 1, 0, createLine('text', ''));
                        linesRef.current = next;
                        return next;
                    });
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
                    setLines((prev) => {
                        const next = [...prev];
                        next.splice(activeIndex + 1, 0, createLine('text', ''));
                        linesRef.current = next;
                        return next;
                    });
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

                setLines((prev) => {
                    const next = [...prev];
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
                    linesRef.current = next;
                    return next;
                });
                cursorPosRef.current = 0;
                setActiveIndex(activeIndex + 1);
                save();
            } else if (e.key === 'Backspace') {
                // Divider active → delete it
                if (line.type === 'divider') {
                    e.preventDefault();
                    setLines((prev) => {
                        const next = [...prev];
                        next.splice(activeIndex, 1);
                        if (next.length === 0)
                            next.push(createLine('text', ''));
                        linesRef.current = next;
                        return next;
                    });
                    const newIdx = Math.max(0, activeIndex - 1);
                    cursorPosRef.current =
                        linesRef.current[newIdx]?.text.length ?? 0;
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
                        if (prev.type === 'divider') {
                            setLines((p) => {
                                const next = [...p];
                                next.splice(activeIndex - 1, 1);
                                linesRef.current = next;
                                return next;
                            });
                            setActiveIndex(activeIndex - 1);
                            cursorPosRef.current = 0;
                        } else {
                            const prevText = prev.text;
                            setLines((p) => {
                                const next = [...p];
                                next[activeIndex - 1] = {
                                    ...next[activeIndex - 1],
                                    text: prevText + next[activeIndex].text,
                                };
                                next.splice(activeIndex, 1);
                                linesRef.current = next;
                                return next;
                            });
                            cursorPosRef.current = prevText.length;
                            setActiveIndex(activeIndex - 1);
                        }
                        save();
                    }
                }
            } else if (e.key === 'ArrowUp' && activeIndex > 0) {
                e.preventDefault();
                cursorPosRef.current = Math.min(
                    input.selectionStart ?? 0,
                    linesRef.current[activeIndex - 1].text.length,
                );
                setActiveIndex(activeIndex - 1);
            } else if (
                e.key === 'ArrowDown' &&
                activeIndex < linesRef.current.length - 1
            ) {
                e.preventDefault();
                cursorPosRef.current = Math.min(
                    input.selectionStart ?? 0,
                    linesRef.current[activeIndex + 1].text.length,
                );
                setActiveIndex(activeIndex + 1);
            }
        },
        [activeIndex, save, updateLine],
    );

    const handleSlashSelect = useCallback(
        (blockType: BlockType) => {
            if (blockType === 'divider') {
                setLines((prev) => {
                    const next = [...prev];
                    next[activeIndex] = {
                        ...next[activeIndex],
                        type: 'divider',
                        text: '',
                    };
                    next.splice(activeIndex + 1, 0, createLine('text', ''));
                    linesRef.current = next;
                    return next;
                });
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
        [activeIndex, updateLine, save],
    );

    const handleSlashClose = useCallback(() => {
        setSlashMenu(null);
        slashDismissedRef.current = true;
        inputRef.current?.focus();
    }, []);

    const handlePaste = useCallback(
        (e: React.ClipboardEvent<HTMLInputElement>) => {
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

            setLines((prev) => {
                const next = [...prev];
                next.splice(activeIndex, 1, ...newLines);
                linesRef.current = next;
                return next;
            });
            const newActiveIndex = activeIndex + newLines.length - 1;
            cursorPosRef.current = newLines[newLines.length - 1].text.length;
            setActiveIndex(newActiveIndex);
            save();
        },
        [activeIndex, save],
    );

    const handleClick = useCallback((index: number) => {
        cursorPosRef.current = linesRef.current[index].text.length;
        setActiveIndex(index);
    }, []);

    return (
        <div
            data-notes-panel
            className="relative flex h-full shrink-0 flex-col border-l border-border bg-surface-sidebar"
        >
            <PanelHeader
                title={t('notes.title')}
                icon={<NotebookPen size={14} className="text-ink-muted" />}
                onClose={onClose}
                suffix={
                    <>
                        {saveStatus !== 'idle' && (
                            <span className="text-[11px] text-ink-faint">
                                {saveStatus === 'saving'
                                    ? t('notes.saving')
                                    : t('notes.saved')}
                            </span>
                        )}
                        <Kbd keys="/" />
                        <Kbd keys="Esc" />
                    </>
                }
            />
            <div className="flex min-h-0 flex-1 flex-col overflow-y-auto p-5">
                {lines.map((line, i) => {
                    const isActive = i === activeIndex;

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
                                    <input
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
                        <button
                            tabIndex={-1}
                            onClick={(e) => {
                                e.stopPropagation();
                                toggleCheck(i);
                            }}
                            className={`mt-[3px] flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded border-[1.5px] transition-colors ${
                                line.checked
                                    ? 'border-accent bg-accent text-white'
                                    : 'border-ink-muted hover:border-ink-soft'
                            }`}
                        >
                            {line.checked && (
                                <svg
                                    className="h-2.5 w-2.5"
                                    viewBox="0 0 10 10"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="1.5"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                >
                                    <path d="M2 5.5l2 2L8 3" />
                                </svg>
                            )}
                        </button>
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
                                <input
                                    ref={inputRef}
                                    data-notes-input
                                    type="text"
                                    value={line.text}
                                    onChange={handleLineChange}
                                    onKeyDown={handleKeyDown}
                                    onPaste={handlePaste}
                                    placeholder={
                                        lines.length === 1 &&
                                        i === 0 &&
                                        line.type === 'text'
                                            ? t('notes.placeholder')
                                            : undefined
                                    }
                                    className={`${textClass} w-full border-0 bg-transparent p-0 font-sans text-ink placeholder:text-ink-faint focus:ring-0 focus:outline-none`}
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
