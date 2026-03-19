import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import FormField from '@/components/ui/FormField';
import Input from '@/components/ui/Input';
import PanelHeader from '@/components/ui/PanelHeader';
import SectionLabel from '@/components/ui/SectionLabel';
import Select from '@/components/ui/Select';
import Textarea from '@/components/ui/Textarea';
import type {
    Act,
    PlotPoint,
    PlotPointConnection,
    Storyline,
} from '@/types/models';

const TYPE_OPTIONS = [
    'setup',
    'conflict',
    'turning_point',
    'resolution',
    'worldbuilding',
] as const;

const STATUS_OPTIONS = ['planned', 'fulfilled', 'abandoned'] as const;

type Props = {
    plotPoint: PlotPoint;
    storylines: Storyline[];
    acts: Act[];
    connections: PlotPointConnection[];
    onClose: () => void;
    onUpdate: (data: Record<string, unknown>) => void;
};

export default function DetailPanel({
    plotPoint,
    acts,
    connections,
    onClose,
    onUpdate,
}: Props) {
    const { t } = useTranslation('plot');
    const [title, setTitle] = useState(plotPoint.title);
    const [description, setDescription] = useState(plotPoint.description ?? '');

    const incomingConnections = connections.filter(
        (c) => c.target_plot_point_id === plotPoint.id,
    );
    const outgoingConnections = connections.filter(
        (c) => c.source_plot_point_id === plotPoint.id,
    );

    const chapterId =
        plotPoint.actual_chapter_id ?? plotPoint.intended_chapter_id;
    const chapter = chapterId
        ? acts
              .flatMap(
                  (a) => (a.chapters ?? []) as { id: number; title: string }[],
              )
              .find((ch) => ch.id === chapterId)
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
            <PanelHeader title={t('detailPanel.header')} onClose={onClose} />

            {/* Scrollable content */}
            <div className="flex flex-1 flex-col gap-4 overflow-y-auto p-4">
                {/* Title */}
                <FormField label={t('detailPanel.title')} className="gap-1">
                    <Input
                        type="text"
                        value={title}
                        onChange={(e) => setTitle(e.target.value)}
                        onBlur={handleTitleBlur}
                        className="font-semibold"
                    />
                </FormField>

                {/* Description */}
                <FormField
                    label={t('detailPanel.description')}
                    className="gap-1"
                >
                    <Textarea
                        value={description}
                        onChange={(e) => setDescription(e.target.value)}
                        onBlur={handleDescriptionBlur}
                        rows={4}
                        placeholder={t('detailPanel.descriptionPlaceholder')}
                    />
                </FormField>

                {/* Type */}
                <FormField label={t('detailPanel.type')} className="gap-1">
                    <Select
                        value={plotPoint.type}
                        onChange={(e) => onUpdate({ type: e.target.value })}
                    >
                        {TYPE_OPTIONS.map((value) => (
                            <option key={value} value={value}>
                                {t(`type.${value}`)}
                            </option>
                        ))}
                    </Select>
                </FormField>

                {/* Status */}
                <FormField label={t('detailPanel.status')} className="gap-1">
                    <Select
                        value={plotPoint.status}
                        onChange={(e) => onUpdate({ status: e.target.value })}
                    >
                        {STATUS_OPTIONS.map((value) => (
                            <option key={value} value={value}>
                                {t(`status.${value}`)}
                            </option>
                        ))}
                    </Select>
                </FormField>

                {/* Connections */}
                {(incomingConnections.length > 0 ||
                    outgoingConnections.length > 0) && (
                    <div className="flex flex-col gap-2">
                        <SectionLabel className="text-[11px]">
                            {t('detailPanel.connections')}
                        </SectionLabel>

                        {incomingConnections.map((conn) => (
                            <div
                                key={conn.id}
                                className="flex flex-col gap-0.5"
                            >
                                <span className="text-[11px] text-ink-muted">
                                    {t(`connection.${conn.type}.incoming`)}
                                </span>
                                <span className="text-[13px] text-ink-soft">
                                    {conn.source?.title ??
                                        t('detailPanel.plotPointFallback', {
                                            id: conn.source_plot_point_id,
                                        })}
                                </span>
                            </div>
                        ))}

                        {outgoingConnections.map((conn) => (
                            <div
                                key={conn.id}
                                className="flex flex-col gap-0.5"
                            >
                                <span className="text-[11px] text-ink-muted">
                                    {t(`connection.${conn.type}.outgoing`)}
                                </span>
                                <span className="text-[13px] text-ink-soft">
                                    {conn.target?.title ??
                                        t('detailPanel.plotPointFallback', {
                                            id: conn.target_plot_point_id,
                                        })}
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
