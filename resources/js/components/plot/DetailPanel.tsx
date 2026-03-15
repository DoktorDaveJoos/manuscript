import type { Act, PlotPoint, PlotPointConnection, Storyline } from '@/types/models';
import { X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

const TYPE_OPTIONS = ['setup', 'conflict', 'turning_point', 'resolution', 'worldbuilding'] as const;

const STATUS_OPTIONS = ['planned', 'fulfilled', 'abandoned'] as const;

type Props = {
    plotPoint: PlotPoint;
    storylines: Storyline[];
    acts: Act[];
    connections: PlotPointConnection[];
    onClose: () => void;
    onUpdate: (data: Record<string, unknown>) => void;
};

export default function DetailPanel({ plotPoint, storylines, acts, connections, onClose, onUpdate }: Props) {
    const { t } = useTranslation('plot');
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
        <aside className="flex h-full w-[320px] shrink-0 flex-col border-l border-border bg-surface-card">
            {/* Header */}
            <div className="flex items-center justify-between border-b border-border px-4 py-3">
                <span className="text-xs font-medium uppercase tracking-[0.06em] text-ink-muted">
                    {t('detailPanel.header')}
                </span>
                <button
                    type="button"
                    onClick={onClose}
                    className="flex size-6 items-center justify-center rounded text-ink-muted transition-colors hover:text-ink-soft"
                >
                    <X size={16} strokeWidth={2.5} />
                </button>
            </div>

            {/* Scrollable content */}
            <div className="flex flex-1 flex-col gap-4 overflow-y-auto p-4">
                {/* Title */}
                <div className="flex flex-col gap-1">
                    <label className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-muted">
                        {t('detailPanel.title')}
                    </label>
                    <input
                        type="text"
                        value={title}
                        onChange={(e) => setTitle(e.target.value)}
                        onBlur={handleTitleBlur}
                        className="rounded border border-border px-2.5 py-1.5 text-[13px] font-semibold text-ink focus:outline-none focus:ring-1 focus:ring-accent"
                    />
                </div>

                {/* Description */}
                <div className="flex flex-col gap-1">
                    <label className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-muted">
                        {t('detailPanel.description')}
                    </label>
                    <textarea
                        value={description}
                        onChange={(e) => setDescription(e.target.value)}
                        onBlur={handleDescriptionBlur}
                        rows={4}
                        placeholder={t('detailPanel.descriptionPlaceholder')}
                        className="resize-none rounded border border-border px-2.5 py-1.5 text-[13px] text-ink-soft placeholder:text-ink-faint focus:outline-none focus:ring-1 focus:ring-accent"
                    />
                </div>

                {/* Type */}
                <div className="flex flex-col gap-1">
                    <label className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-muted">
                        {t('detailPanel.type')}
                    </label>
                    <select
                        value={plotPoint.type}
                        onChange={(e) => onUpdate({ type: e.target.value })}
                        className="rounded border border-border px-2.5 py-1.5 text-[13px] text-ink-soft focus:outline-none focus:ring-1 focus:ring-accent"
                    >
                        {TYPE_OPTIONS.map((value) => (
                            <option key={value} value={value}>
                                {t(`type.${value}`)}
                            </option>
                        ))}
                    </select>
                </div>

                {/* Status */}
                <div className="flex flex-col gap-1">
                    <label className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-muted">
                        {t('detailPanel.status')}
                    </label>
                    <select
                        value={plotPoint.status}
                        onChange={(e) => onUpdate({ status: e.target.value })}
                        className="rounded border border-border px-2.5 py-1.5 text-[13px] text-ink-soft focus:outline-none focus:ring-1 focus:ring-accent"
                    >
                        {STATUS_OPTIONS.map((value) => (
                            <option key={value} value={value}>
                                {t(`status.${value}`)}
                            </option>
                        ))}
                    </select>
                </div>

                {/* Connections */}
                {(incomingConnections.length > 0 || outgoingConnections.length > 0) && (
                    <div className="flex flex-col gap-2">
                        <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-muted">
                            {t('detailPanel.connections')}
                        </span>

                        {incomingConnections.map((conn) => (
                            <div key={conn.id} className="flex flex-col gap-0.5">
                                <span className="text-[11px] text-ink-muted">
                                    {t(`connection.${conn.type}.incoming`)}
                                </span>
                                <span className="text-[13px] text-ink-soft">
                                    {conn.source?.title ??
                                        t('detailPanel.plotPointFallback', { id: conn.source_plot_point_id })}
                                </span>
                            </div>
                        ))}

                        {outgoingConnections.map((conn) => (
                            <div key={conn.id} className="flex flex-col gap-0.5">
                                <span className="text-[11px] text-ink-muted">
                                    {t(`connection.${conn.type}.outgoing`)}
                                </span>
                                <span className="text-[13px] text-ink-soft">
                                    {conn.target?.title ??
                                        t('detailPanel.plotPointFallback', { id: conn.target_plot_point_id })}
                                </span>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Jump to chapter */}
            {chapter && (
                <div className="border-t border-border px-4 py-3">
                    <a
                        href={`/books/${plotPoint.book_id}/chapters/${chapterId}`}
                        className="flex w-full items-center justify-center rounded bg-neutral-bg px-3 py-2 text-[13px] font-medium text-ink-soft transition-colors hover:bg-border"
                    >
                        {t('detailPanel.jumpToChapter')}
                    </a>
                </div>
            )}
        </aside>
    );
}
