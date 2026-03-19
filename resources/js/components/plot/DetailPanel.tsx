import { X } from 'lucide-react';
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
    Character,
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
    bookCharacters: Character[];
    onClose: () => void;
    onUpdate: (data: Record<string, unknown>) => void;
};

export default function DetailPanel({
    plotPoint,
    acts,
    connections,
    bookCharacters,
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

                {/* Characters */}
                <div className="flex flex-col gap-2">
                    <span className="text-[11px] font-medium tracking-[0.08em] text-ink-muted uppercase">
                        {t('detailPanel.characters')}
                    </span>

                    {(plotPoint.characters ?? []).map((char) => (
                        <div
                            key={char.id}
                            className="flex items-center justify-between gap-2"
                        >
                            <div className="flex items-center gap-2">
                                <span className="flex h-5 w-5 items-center justify-center rounded-full bg-neutral-bg text-[10px] font-semibold text-ink-soft uppercase">
                                    {char.name.charAt(0)}
                                </span>
                                <span className="text-[13px] text-ink-soft">
                                    {char.name}
                                </span>
                            </div>
                            <div className="flex items-center gap-1">
                                <select
                                    value={char.pivot?.role ?? 'key'}
                                    onChange={(e) => {
                                        const updated = (
                                            plotPoint.characters ?? []
                                        ).map((c) =>
                                            c.id === char.id
                                                ? {
                                                      id: c.id,
                                                      role: e.target.value,
                                                  }
                                                : {
                                                      id: c.id,
                                                      role:
                                                          c.pivot?.role ??
                                                          'key',
                                                  },
                                        );
                                        onUpdate({ characters: updated });
                                    }}
                                    className="rounded border border-border bg-surface-card px-1 py-0.5 text-[11px] text-ink-soft"
                                >
                                    <option value="key">
                                        {t('detailPanel.characterRole.key')}
                                    </option>
                                    <option value="supporting">
                                        {t(
                                            'detailPanel.characterRole.supporting',
                                        )}
                                    </option>
                                    <option value="mentioned">
                                        {t(
                                            'detailPanel.characterRole.mentioned',
                                        )}
                                    </option>
                                </select>
                                <button
                                    type="button"
                                    onClick={() => {
                                        const updated = (
                                            plotPoint.characters ?? []
                                        )
                                            .filter((c) => c.id !== char.id)
                                            .map((c) => ({
                                                id: c.id,
                                                role: c.pivot?.role ?? 'key',
                                            }));
                                        onUpdate({ characters: updated });
                                    }}
                                    className="flex h-5 w-5 items-center justify-center rounded text-ink-faint hover:text-ink-soft"
                                >
                                    <X size={12} />
                                </button>
                            </div>
                        </div>
                    ))}

                    {(() => {
                        const taggedIds = new Set(
                            (plotPoint.characters ?? []).map((c) => c.id),
                        );
                        const available = bookCharacters.filter(
                            (c) => !taggedIds.has(c.id),
                        );
                        if (available.length === 0) return null;
                        return (
                            <select
                                value=""
                                onChange={(e) => {
                                    const charId = Number(e.target.value);
                                    if (!charId) return;
                                    const updated = [
                                        ...(plotPoint.characters ?? []).map(
                                            (c) => ({
                                                id: c.id,
                                                role: c.pivot?.role ?? 'key',
                                            }),
                                        ),
                                        { id: charId, role: 'key' },
                                    ];
                                    onUpdate({ characters: updated });
                                }}
                                className="rounded border border-dashed border-border bg-transparent px-2 py-1.5 text-[12px] text-ink-muted"
                            >
                                <option value="">
                                    {t('detailPanel.addCharacter')}
                                </option>
                                {available.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                        );
                    })()}
                </div>

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
