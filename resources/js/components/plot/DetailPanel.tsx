import { CONNECTION_LABELS, TYPE_LABELS } from '@/lib/plot-constants';
import type { Act, PlotPoint, PlotPointConnection, Storyline } from '@/types/models';
import { X } from '@phosphor-icons/react';
import { useState } from 'react';

const TYPE_OPTIONS = Object.entries(TYPE_LABELS).map(([value, label]) => ({ value, label }));

const STATUS_OPTIONS = [
    { value: 'planned', label: 'Planned' },
    { value: 'fulfilled', label: 'Fulfilled' },
    { value: 'abandoned', label: 'Abandoned' },
] as const;

type Props = {
    plotPoint: PlotPoint;
    storylines: Storyline[];
    acts: Act[];
    connections: PlotPointConnection[];
    onClose: () => void;
    onUpdate: (data: Record<string, unknown>) => void;
};

export default function DetailPanel({ plotPoint, storylines, acts, connections, onClose, onUpdate }: Props) {
    const [title, setTitle] = useState(plotPoint.title);
    const [description, setDescription] = useState(plotPoint.description ?? '');

    const incomingConnections = connections.filter((c) => c.target_plot_point_id === plotPoint.id);
    const outgoingConnections = connections.filter((c) => c.source_plot_point_id === plotPoint.id);

    const chapterId = plotPoint.actual_chapter_id ?? plotPoint.intended_chapter_id;
    const chapter = chapterId
        ? acts.flatMap((a) => (a.chapters ?? []) as { id: number; title: string }[]).find((ch) => ch.id === chapterId)
        : null;

    const handleTitleBlur = () => {
        if (title !== plotPoint.title) {
            onUpdate({ title });
        }
    };

    const handleDescriptionBlur = () => {
        if (description !== (plotPoint.description ?? '')) {
            onUpdate({ description });
        }
    };

    return (
        <aside className="flex h-full w-[320px] shrink-0 flex-col border-l border-[#ECEAE4] bg-white">
            {/* Header */}
            <div className="flex items-center justify-between border-b border-[#ECEAE4] px-4 py-3">
                <span className="text-xs font-medium uppercase tracking-[0.06em] text-[#8A857D]">Plot Point</span>
                <button
                    type="button"
                    onClick={onClose}
                    className="flex size-6 items-center justify-center rounded text-[#8A857D] transition-colors hover:text-[#5A574F]"
                >
                    <X size={16} weight="bold" />
                </button>
            </div>

            {/* Scrollable content */}
            <div className="flex flex-1 flex-col gap-4 overflow-y-auto p-4">
                {/* Title */}
                <div className="flex flex-col gap-1">
                    <label className="text-[11px] font-medium uppercase tracking-[0.06em] text-[#8A857D]">Title</label>
                    <input
                        type="text"
                        value={title}
                        onChange={(e) => setTitle(e.target.value)}
                        onBlur={handleTitleBlur}
                        className="rounded border border-[#ECEAE4] px-2.5 py-1.5 text-[13px] font-semibold text-[#1A1A1A] focus:outline-none focus:ring-1 focus:ring-[#C8B88A]"
                    />
                </div>

                {/* Description */}
                <div className="flex flex-col gap-1">
                    <label className="text-[11px] font-medium uppercase tracking-[0.06em] text-[#8A857D]">
                        Description
                    </label>
                    <textarea
                        value={description}
                        onChange={(e) => setDescription(e.target.value)}
                        onBlur={handleDescriptionBlur}
                        rows={4}
                        placeholder="Add a description..."
                        className="resize-none rounded border border-[#ECEAE4] px-2.5 py-1.5 text-[13px] text-[#5A574F] placeholder:text-[#B0A99F] focus:outline-none focus:ring-1 focus:ring-[#C8B88A]"
                    />
                </div>

                {/* Type */}
                <div className="flex flex-col gap-1">
                    <label className="text-[11px] font-medium uppercase tracking-[0.06em] text-[#8A857D]">Type</label>
                    <select
                        value={plotPoint.type}
                        onChange={(e) => onUpdate({ type: e.target.value })}
                        className="rounded border border-[#ECEAE4] px-2.5 py-1.5 text-[13px] text-[#5A574F] focus:outline-none focus:ring-1 focus:ring-[#C8B88A]"
                    >
                        {TYPE_OPTIONS.map((opt) => (
                            <option key={opt.value} value={opt.value}>
                                {opt.label}
                            </option>
                        ))}
                    </select>
                </div>

                {/* Status */}
                <div className="flex flex-col gap-1">
                    <label className="text-[11px] font-medium uppercase tracking-[0.06em] text-[#8A857D]">
                        Status
                    </label>
                    <select
                        value={plotPoint.status}
                        onChange={(e) => onUpdate({ status: e.target.value })}
                        className="rounded border border-[#ECEAE4] px-2.5 py-1.5 text-[13px] text-[#5A574F] focus:outline-none focus:ring-1 focus:ring-[#C8B88A]"
                    >
                        {STATUS_OPTIONS.map((opt) => (
                            <option key={opt.value} value={opt.value}>
                                {opt.label}
                            </option>
                        ))}
                    </select>
                </div>

                {/* Connections */}
                {(incomingConnections.length > 0 || outgoingConnections.length > 0) && (
                    <div className="flex flex-col gap-2">
                        <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-[#8A857D]">
                            Connections
                        </span>

                        {incomingConnections.map((conn) => (
                            <div key={conn.id} className="flex flex-col gap-0.5">
                                <span className="text-[11px] text-[#8A857D]">
                                    {CONNECTION_LABELS[conn.type]?.incoming ?? conn.type}
                                </span>
                                <span className="text-[13px] text-[#5A574F]">
                                    {conn.source?.title ?? `Plot point #${conn.source_plot_point_id}`}
                                </span>
                            </div>
                        ))}

                        {outgoingConnections.map((conn) => (
                            <div key={conn.id} className="flex flex-col gap-0.5">
                                <span className="text-[11px] text-[#8A857D]">
                                    {CONNECTION_LABELS[conn.type]?.outgoing ?? conn.type}
                                </span>
                                <span className="text-[13px] text-[#5A574F]">
                                    {conn.target?.title ?? `Plot point #${conn.target_plot_point_id}`}
                                </span>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Jump to chapter */}
            {chapter && (
                <div className="border-t border-[#ECEAE4] px-4 py-3">
                    <a
                        href={`/books/${plotPoint.book_id}/chapters/${chapterId}`}
                        className="flex w-full items-center justify-center rounded bg-[#F0EEEA] px-3 py-2 text-[13px] font-medium text-[#5A574F] transition-colors hover:bg-[#E8E5DF]"
                    >
                        Jump to chapter
                    </a>
                </div>
            )}
        </aside>
    );
}
