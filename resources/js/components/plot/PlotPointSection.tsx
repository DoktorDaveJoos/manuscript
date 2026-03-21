import { useDroppable } from '@dnd-kit/core';
import {
    SortableContext,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { EllipsisVertical, GripVertical, Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';
import type { Beat, PlotPoint } from '@/types/models';
import BeatCard from './BeatCard';

type Props = {
    plotPoint: PlotPoint & { beats?: Beat[] };
    selectedBeatId: number | null;
    onSelectBeat: (beat: Beat) => void;
    onCreateBeat: (plotPointId: number) => void;
    onDeletePlotPoint: (plotPointId: number) => void;
    onBeatContextMenu: (beat: Beat, position: { x: number; y: number }) => void;
};

export default function PlotPointSection({
    plotPoint,
    selectedBeatId,
    onSelectBeat,
    onCreateBeat,
    onDeletePlotPoint,
    onBeatContextMenu,
}: Props) {
    const { t } = useTranslation('plot');
    const [menuOpen, setMenuOpen] = useState(false);
    const menuRef = useRef<HTMLDivElement>(null);

    const {
        attributes,
        listeners,
        setNodeRef: setSortableRef,
        transform,
        transition,
        isDragging,
    } = useSortable({
        id: `plotpoint-${plotPoint.id}`,
        data: { type: 'plotpoint', plotPoint },
    });

    const { setNodeRef: setDroppableRef, isOver } = useDroppable({
        id: `plotpoint-drop-${plotPoint.id}`,
        data: { type: 'plotpoint-drop', plotPointId: plotPoint.id },
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    useEffect(() => {
        if (!menuOpen) return;

        function handleClickOutside(e: MouseEvent) {
            if (
                menuRef.current &&
                !menuRef.current.contains(e.target as Node)
            ) {
                setMenuOpen(false);
            }
        }

        document.addEventListener('mousedown', handleClickOutside);
        return () =>
            document.removeEventListener('mousedown', handleClickOutside);
    }, [menuOpen]);

    const beats = plotPoint.beats ?? [];
    const beatIds = useMemo(() => beats.map((b) => `beat-${b.id}`), [beats]);

    return (
        <div
            ref={(node) => {
                setSortableRef(node);
                setDroppableRef(node);
            }}
            style={{
                ...style,
                backgroundColor: isOver ? '#F7F5F0' : '#FCFAF7',
                borderColor: isOver ? '#C49A6C' : '#E4E2DD',
                borderRadius: 8,
            }}
            {...attributes}
            className={cn(
                'flex flex-col gap-2.5 rounded-lg border p-3.5 transition-colors',
                isDragging && 'opacity-50',
            )}
        >
            {/* Header */}
            <div className="flex items-center justify-between gap-2">
                <div className="flex items-center gap-1.5">
                    <span
                        {...listeners}
                        className="flex shrink-0 cursor-grab items-center text-ink-faint active:cursor-grabbing"
                    >
                        <GripVertical className="h-3.5 w-3.5" />
                    </span>
                    <span
                        className="text-[13px] leading-tight font-semibold"
                        style={{ color: '#141414' }}
                    >
                        {plotPoint.title}
                    </span>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    <span
                        className="rounded px-2 py-0.5 text-[10px] font-medium"
                        style={{
                            backgroundColor: '#F0EEEB',
                            borderRadius: 4,
                            color: '#737373',
                        }}
                    >
                        {t(`type.${plotPoint.type}`)}
                    </span>
                    <div ref={menuRef} className="relative">
                        <button
                            type="button"
                            onClick={(e) => {
                                e.stopPropagation();
                                setMenuOpen(!menuOpen);
                            }}
                            className="flex items-center justify-center rounded transition-colors hover:bg-black/5"
                            style={{ width: 20, height: 20 }}
                        >
                            <EllipsisVertical
                                size={14}
                                style={{ color: '#A3A3A3' }}
                            />
                        </button>
                        {menuOpen && (
                            <div className="absolute top-full right-0 z-50 mt-1 w-[160px] rounded-lg bg-surface-card shadow-[0_4px_24px_#0000001F,0_0_0_1px_#0000000A]">
                                <div className="flex flex-col p-1">
                                    <button
                                        type="button"
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            setMenuOpen(false);
                                            onDeletePlotPoint(plotPoint.id);
                                        }}
                                        className="flex w-full items-center gap-2.5 rounded-[5px] px-3 py-2 text-left text-[13px] font-medium text-delete transition-colors hover:bg-neutral-bg"
                                    >
                                        <Trash2 size={14} />
                                        {t('detailPanel.header', 'Delete')}
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Beats list */}
            <SortableContext
                items={beatIds}
                strategy={verticalListSortingStrategy}
            >
                {beats.length > 0 && (
                    <div className="flex flex-col gap-2 pl-1">
                        {beats.map((beat) => (
                            <BeatCard
                                key={beat.id}
                                beat={beat}
                                isSelected={selectedBeatId === beat.id}
                                onClick={() => onSelectBeat(beat)}
                                onContextMenu={(e) => {
                                    e.preventDefault();
                                    onBeatContextMenu(beat, {
                                        x: e.clientX,
                                        y: e.clientY,
                                    });
                                }}
                            />
                        ))}
                    </div>
                )}
            </SortableContext>

            {/* Empty drop zone when no beats */}
            {beats.length === 0 && isOver && (
                <div
                    className="rounded border border-dashed py-3 text-center text-[11px] text-ink-faint"
                    style={{ borderColor: '#C49A6C' }}
                >
                    {t('beat.dropHere', 'Drop here')}
                </div>
            )}

            {/* Add beat button */}
            <button
                type="button"
                onClick={() => onCreateBeat(plotPoint.id)}
                className="flex items-center gap-1 pt-1 transition-opacity hover:opacity-70"
            >
                <Plus size={12} style={{ color: '#A3A3A3' }} />
                <span
                    className="text-[11px] font-normal"
                    style={{ color: '#A3A3A3' }}
                >
                    {t('beat.addBeat')}
                </span>
            </button>
        </div>
    );
}
