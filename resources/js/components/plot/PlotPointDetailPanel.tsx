import { router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import Button from '@/components/ui/Button';
import Drawer from '@/components/ui/Drawer';
import FormField from '@/components/ui/FormField';
import Input from '@/components/ui/Input';
import PanelHeader from '@/components/ui/PanelHeader';
import SectionLabel from '@/components/ui/SectionLabel';
import Textarea from '@/components/ui/Textarea';
import { useDebouncedCallback } from '@/hooks/useDebouncedCallback';
import { STATUS_PILL_OPTIONS } from '@/lib/plot-constants';
import type { PlotPoint, PlotPointStatus, PlotPointType } from '@/types/models';
import StatusPillGroup from './StatusPillGroup';

const TYPE_OPTIONS: {
    value: PlotPointType;
    labelKey: string;
    activeClass: string;
}[] = [
    {
        value: 'setup',
        labelKey: 'type.setup',
        activeClass: 'bg-ink/10 text-ink',
    },
    {
        value: 'conflict',
        labelKey: 'type.conflict',
        activeClass: 'bg-ink/10 text-ink',
    },
    {
        value: 'turning_point',
        labelKey: 'typeShort.turning_point',
        activeClass: 'bg-ink/10 text-ink',
    },
    {
        value: 'resolution',
        labelKey: 'type.resolution',
        activeClass: 'bg-ink/10 text-ink',
    },
    {
        value: 'worldbuilding',
        labelKey: 'typeShort.worldbuilding',
        activeClass: 'bg-ink/10 text-ink',
    },
];

type Props = {
    plotPoint: PlotPoint;
    bookId: number;
    onClose: () => void;
    onDelete: (plotPointId: number) => void;
    onTitleChange?: (title: string) => void;
};

export default function PlotPointDetailPanel({
    plotPoint,
    bookId,
    onClose,
    onDelete,
    onTitleChange,
}: Props) {
    const { t } = useTranslation('plot');
    const [title, setTitle] = useState(plotPoint.title);
    const [description, setDescription] = useState(plotPoint.description ?? '');

    const patchPlotPoint = useCallback(
        (data: Record<string, string | number>) => {
            router.patch(`/books/${bookId}/plot-points/${plotPoint.id}`, data, {
                preserveScroll: true,
            });
        },
        [bookId, plotPoint.id],
    );

    const debouncedPatchTitle = useDebouncedCallback(
        (title: string) => patchPlotPoint({ title }),
        500,
    );

    const debouncedPatchDescription = useDebouncedCallback(
        (description: string) => patchPlotPoint({ description }),
        500,
    );

    const handleTitleChange = (value: string) => {
        setTitle(value);
        onTitleChange?.(value);
        debouncedPatchTitle(value);
    };

    const handleDescriptionChange = (value: string) => {
        setDescription(value);
        debouncedPatchDescription(value);
    };

    const handleTypeChange = useCallback(
        (type: PlotPointType) => {
            patchPlotPoint({ type });
        },
        [patchPlotPoint],
    );

    const handleStatusChange = useCallback(
        (status: PlotPointStatus) => {
            router.patch(
                `/books/${bookId}/plot-points/${plotPoint.id}/status`,
                { status },
                { preserveScroll: true },
            );
        },
        [bookId, plotPoint.id],
    );

    return (
        <Drawer onClose={onClose}>
            <PanelHeader title={t('plotPoint.header')} onClose={onClose} />

            <div className="flex flex-1 flex-col gap-5 overflow-y-auto p-5">
                {/* Title */}
                <FormField label={t('plotPoint.title')}>
                    <Input
                        type="text"
                        value={title}
                        onChange={(e) => handleTitleChange(e.target.value)}
                    />
                </FormField>

                {/* Description */}
                <FormField label={t('plotPoint.description')}>
                    <Textarea
                        value={description}
                        onChange={(e) =>
                            handleDescriptionChange(e.target.value)
                        }
                        rows={4}
                        placeholder={t('plotPoint.descriptionPlaceholder')}
                    />
                    <span className="text-[10px] text-ink-faint italic">
                        {t(
                            'plotPoint.descriptionHelper',
                            'Summarize what happens at this turning point.',
                        )}
                    </span>
                </FormField>

                {/* Type */}
                <FormField label={t('plotPoint.type')}>
                    <StatusPillGroup
                        options={TYPE_OPTIONS}
                        value={plotPoint.type}
                        onChange={handleTypeChange}
                    />
                    <span className="text-[10px] text-ink-faint italic">
                        {t(
                            'plotPoint.typeHelper',
                            'How this point functions in the narrative arc.',
                        )}
                    </span>
                </FormField>

                {/* Status */}
                <FormField label={t('plotPoint.status')}>
                    <StatusPillGroup
                        options={STATUS_PILL_OPTIONS}
                        value={plotPoint.status}
                        onChange={handleStatusChange}
                    />
                    <span className="text-[10px] text-ink-faint italic">
                        {t(
                            'plotPoint.statusHelper',
                            'Track whether this plot point has been resolved.',
                        )}
                    </span>
                </FormField>

                {/* Divider */}
                <div className="h-px bg-border" />

                {/* Characters */}
                <div className="flex flex-col gap-2.5">
                    <div className="flex items-center justify-between">
                        <SectionLabel>{t('plotPoint.characters')}</SectionLabel>
                        <button
                            type="button"
                            className="flex items-center justify-center text-ink-muted transition-colors hover:text-ink-soft"
                            aria-label={t('detailPanel.addCharacter')}
                        >
                            <Plus size={14} />
                        </button>
                    </div>
                    {plotPoint.characters &&
                        plotPoint.characters.length > 0 && (
                            <div className="flex flex-wrap gap-1.5">
                                {plotPoint.characters.map((char) => (
                                    <span
                                        key={char.id}
                                        className="rounded-full bg-neutral-bg px-2.5 py-1 text-[11px] font-medium text-ink-soft"
                                    >
                                        {char.name}
                                        <span className="ml-1 text-ink-muted">
                                            {t(
                                                `detailPanel.characterRole.${char.pivot.role}`,
                                            )}
                                        </span>
                                    </span>
                                ))}
                            </div>
                        )}
                    <span className="text-[10px] text-ink-faint italic">
                        {t(
                            'plotPoint.characterHelper',
                            'Tag characters involved in this plot point.',
                        )}
                    </span>
                </div>

                {/* Spacer */}
                <div className="flex-1" />

                {/* Delete */}
                <Button
                    variant="danger"
                    onClick={() => onDelete(plotPoint.id)}
                    className="w-full py-2.5"
                >
                    <Trash2 size={14} />
                    {t('plotPoint.delete')}
                </Button>
            </div>
        </Drawer>
    );
}
