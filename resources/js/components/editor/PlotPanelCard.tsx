import { Ellipsis, ExternalLink, Link2Off, Plus, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/DropdownMenu';
import SectionLabel from '@/components/ui/SectionLabel';
import Textarea from '@/components/ui/Textarea';
import DescriptionBlock from '@/components/wiki/DescriptionBlock';
import { cn } from '@/lib/utils';
import type { BeatStatus } from '@/types/models';

const STATUS_OPTIONS: BeatStatus[] = ['planned', 'fulfilled', 'abandoned'];

const STATUS_TONE: Record<BeatStatus, string> = {
    planned: 'bg-neutral-bg text-ink-muted',
    fulfilled: 'bg-plot-resolution-bg text-plot-resolution-text',
    abandoned: 'bg-neutral-bg text-ink-faint',
};

export type PlotPanelBeat = {
    id: number;
    title: string;
    description: string | null;
    status: BeatStatus;
    sort_order: number;
    plot_point_id: number;
    chapters: { id: number; title: string }[];
};

type Props = {
    beat: PlotPanelBeat;
    isConnected: boolean;
    onConnect?: () => void;
    onDisconnect?: () => void;
    onDismiss?: () => void;
    onUpdate?: (data: {
        title?: string;
        description?: string | null;
        status?: BeatStatus;
    }) => void;
    plotBoardUrl: string;
};

export default function PlotPanelCard({
    beat,
    isConnected,
    onConnect,
    onDisconnect,
    onDismiss,
    onUpdate,
    plotBoardUrl,
}: Props) {
    const { t } = useTranslation('plot-panel');

    return (
        <div className="flex flex-col gap-2.5 rounded-lg bg-surface-card p-3 ring-1 ring-border-light">
            <div className="flex items-start gap-2">
                <EditableTitle
                    value={beat.title}
                    onCommit={(next) => onUpdate?.({ title: next })}
                    readOnly={!onUpdate}
                />
                <StatusBadge
                    status={beat.status}
                    onChange={
                        onUpdate
                            ? (next) => onUpdate({ status: next })
                            : undefined
                    }
                />
                {isConnected ? (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button
                                type="button"
                                aria-label="More actions"
                                className="shrink-0 rounded-md p-1 text-ink-faint transition-colors hover:bg-neutral-bg hover:text-ink"
                            >
                                <Ellipsis className="size-3.5" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" sideOffset={4}>
                            <DropdownMenuItem
                                onClick={() =>
                                    window.open(plotBoardUrl, '_blank')
                                }
                            >
                                <ExternalLink className="size-3.5" />
                                {t('viewOnPlotBoard')}
                            </DropdownMenuItem>
                            {onDisconnect && (
                                <DropdownMenuItem
                                    onClick={onDisconnect}
                                    className="text-delete focus:text-delete"
                                >
                                    <Link2Off className="size-3.5" />
                                    {t('disconnectFromChapter')}
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                ) : onDismiss ? (
                    <button
                        type="button"
                        onClick={onDismiss}
                        aria-label={t('dismiss')}
                        className="shrink-0 rounded-md p-1 text-ink-faint transition-colors hover:bg-neutral-bg hover:text-ink"
                    >
                        <X className="size-3.5" />
                    </button>
                ) : null}
            </div>

            <EditableDescription
                value={beat.description ?? ''}
                onCommit={(next) =>
                    onUpdate?.({ description: next === '' ? null : next })
                }
                readOnly={!onUpdate}
                placeholder={t('description')}
            />

            {!isConnected && onConnect && (
                <button
                    type="button"
                    onClick={onConnect}
                    className="flex items-center justify-center gap-1.5 rounded-md bg-ink px-2.5 py-1 text-[12px] font-medium text-surface transition-colors hover:bg-ink-muted"
                >
                    <Plus className="size-3" />
                    {t('connectToChapter')}
                </button>
            )}
        </div>
    );
}

function EditableTitle({
    value,
    onCommit,
    readOnly,
}: {
    value: string;
    onCommit: (next: string) => void;
    readOnly?: boolean;
}) {
    const [editing, setEditing] = useState(false);
    const [local, setLocal] = useState(value);
    const [syncedValue, setSyncedValue] = useState(value);
    const inputRef = useRef<HTMLInputElement>(null);

    if (value !== syncedValue && !editing) {
        setSyncedValue(value);
        setLocal(value);
    }

    useEffect(() => {
        if (editing) inputRef.current?.focus();
    }, [editing]);

    const commit = () => {
        const next = local.trim();
        if (next && next !== value) onCommit(next);
        else setLocal(value);
        setEditing(false);
    };

    if (readOnly || !editing) {
        return (
            <button
                type="button"
                onClick={() => !readOnly && setEditing(true)}
                disabled={readOnly}
                className="min-w-0 flex-1 truncate text-left text-[13px] font-medium text-ink disabled:cursor-default"
            >
                {value}
            </button>
        );
    }

    return (
        <input
            ref={inputRef}
            value={local}
            onChange={(e) => setLocal(e.target.value)}
            onBlur={commit}
            onKeyDown={(e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    commit();
                } else if (e.key === 'Escape') {
                    setLocal(value);
                    setEditing(false);
                }
            }}
            className="min-w-0 flex-1 rounded-md border border-border bg-surface-card px-1.5 py-0.5 text-[13px] font-medium text-ink focus:ring-1 focus:ring-ink focus:outline-none"
        />
    );
}

function EditableDescription({
    value,
    onCommit,
    readOnly,
    placeholder,
}: {
    value: string;
    onCommit: (next: string) => void;
    readOnly?: boolean;
    placeholder: string;
}) {
    const [editing, setEditing] = useState(false);
    const [local, setLocal] = useState(value);
    const [syncedValue, setSyncedValue] = useState(value);
    const textareaRef = useRef<HTMLTextAreaElement>(null);

    if (value !== syncedValue && !editing) {
        setSyncedValue(value);
        setLocal(value);
    }

    useEffect(() => {
        if (!editing) return;
        const el = textareaRef.current;
        if (!el) return;
        el.focus();
        el.style.height = 'auto';
        el.style.height = `${el.scrollHeight}px`;
    }, [editing]);

    useEffect(() => {
        if (!editing) return;
        const el = textareaRef.current;
        if (!el) return;
        el.style.height = 'auto';
        el.style.height = `${el.scrollHeight}px`;
    }, [editing, local]);

    const commit = () => {
        if (local !== value) onCommit(local);
        setEditing(false);
    };

    if (editing && !readOnly) {
        return (
            <Textarea
                ref={textareaRef}
                value={local}
                onChange={(e) => setLocal(e.target.value)}
                onBlur={commit}
                onKeyDown={(e) => {
                    if (e.key === 'Escape') {
                        setLocal(value);
                        setEditing(false);
                    }
                }}
                placeholder={placeholder}
                rows={1}
                className="min-h-0 px-2 py-1.5 text-[12px] leading-relaxed"
            />
        );
    }

    if (value.trim() === '') {
        return (
            <button
                type="button"
                onClick={() => !readOnly && setEditing(true)}
                disabled={readOnly}
                className="rounded-md px-2 py-1.5 text-left text-[12px] text-ink-faint italic transition-colors hover:bg-neutral-bg disabled:cursor-default disabled:hover:bg-transparent"
            >
                {placeholder}
            </button>
        );
    }

    return (
        <button
            type="button"
            onClick={() => !readOnly && setEditing(true)}
            disabled={readOnly}
            className="block w-full rounded-md px-2 py-1.5 text-left transition-colors hover:bg-neutral-bg disabled:cursor-default disabled:hover:bg-transparent"
        >
            <DescriptionBlock
                text={value}
                className="text-[12px] leading-relaxed text-ink-muted"
            />
        </button>
    );
}

function StatusBadge({
    status,
    onChange,
}: {
    status: BeatStatus;
    onChange?: (next: BeatStatus) => void;
}) {
    const { t } = useTranslation('plot-panel');

    if (!onChange) {
        return (
            <span
                className={cn(
                    'shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium tracking-wide uppercase',
                    STATUS_TONE[status],
                )}
            >
                {t(`status.${status}`)}
            </span>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className={cn(
                        'shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium tracking-wide uppercase transition-colors',
                        STATUS_TONE[status],
                    )}
                >
                    {t(`status.${status}`)}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" sideOffset={4}>
                <SectionLabel
                    variant="section"
                    className="px-2 pt-1.5 pb-1 text-ink-faint"
                >
                    {t('status')}
                </SectionLabel>
                {STATUS_OPTIONS.map((opt) => (
                    <DropdownMenuItem
                        key={opt}
                        onClick={() => onChange(opt)}
                        className={cn(opt === status && 'font-medium')}
                    >
                        <span
                            className={cn(
                                'inline-block size-2 rounded-full',
                                STATUS_TONE[opt].split(' ')[0],
                            )}
                        />
                        {t(`status.${opt}`)}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
