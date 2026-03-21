import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import Button from '@/components/ui/Button';
import Drawer from '@/components/ui/Drawer';
import FormField from '@/components/ui/FormField';
import Input from '@/components/ui/Input';
import PanelHeader from '@/components/ui/PanelHeader';
import SectionLabel from '@/components/ui/SectionLabel';
import Textarea from '@/components/ui/Textarea';
import { useDebouncedCallback } from '@/hooks/useDebouncedCallback';
import type { Act, Beat, PlotPoint } from '@/types/models';

type Props = {
    act: Act;
    bookId: number;
    plotPoints: (PlotPoint & { beats?: Beat[] })[];
    onClose: () => void;
    onDelete: (actId: number) => void;
    onTitleChange?: (title: string) => void;
};

export default function ActDetailPanel({
    act,
    bookId,
    plotPoints,
    onClose,
    onDelete,
    onTitleChange,
}: Props) {
    const { t } = useTranslation('plot');
    const titleRef = useRef<HTMLInputElement>(null);
    const [number, setNumber] = useState(String(act.number));
    const [title, setTitle] = useState(act.title);
    const [description, setDescription] = useState(act.description ?? '');

    useEffect(() => {
        titleRef.current?.focus();
        titleRef.current?.select();
    }, []);

    const summary = useMemo(() => {
        const allBeats = plotPoints.flatMap((pp) => pp.beats ?? []);
        const fulfilled = allBeats.filter(
            (b) => b.status === 'fulfilled',
        ).length;
        return {
            plotPointCount: plotPoints.length,
            beatCount: allBeats.length,
            fulfilledCount: fulfilled,
        };
    }, [plotPoints]);

    const patchAct = useCallback(
        (data: Record<string, string | number>) => {
            router.patch(`/books/${bookId}/acts/${act.id}`, data, {
                preserveScroll: true,
            });
        },
        [bookId, act.id],
    );

    const debouncedPatchNumber = useDebouncedCallback(
        (number: number) => patchAct({ number }),
        500,
    );

    const debouncedPatchTitle = useDebouncedCallback(
        (title: string) => patchAct({ title }),
        500,
    );

    const debouncedPatchDescription = useDebouncedCallback(
        (description: string) => patchAct({ description }),
        500,
    );

    const handleNumberChange = (value: string) => {
        setNumber(value);
        const parsed = parseInt(value, 10);
        if (!isNaN(parsed)) {
            debouncedPatchNumber(parsed);
        }
    };

    const handleTitleChange = (value: string) => {
        setTitle(value);
        onTitleChange?.(value);
        debouncedPatchTitle(value);
    };

    const handleDescriptionChange = (value: string) => {
        setDescription(value);
        debouncedPatchDescription(value);
    };

    return (
        <Drawer onClose={onClose}>
            <PanelHeader title={t('act.details', 'Act')} onClose={onClose} />

            <div className="flex flex-1 flex-col gap-5 overflow-y-auto p-5">
                {/* Act Number */}
                <FormField label={t('act.number', 'Act Number')}>
                    <Input
                        type="number"
                        value={number}
                        onChange={(e) => handleNumberChange(e.target.value)}
                        className="w-20"
                        min={1}
                    />
                    <span className="text-[10px] text-ink-faint italic">
                        {t(
                            'act.numberHelper',
                            'Controls the display order across the timeline.',
                        )}
                    </span>
                </FormField>

                {/* Title */}
                <FormField label={t('act.title', 'Title')}>
                    <Input
                        ref={titleRef}
                        type="text"
                        value={title}
                        onChange={(e) => handleTitleChange(e.target.value)}
                    />
                    <span className="text-[10px] text-ink-faint italic">
                        {t(
                            'act.titleHelper',
                            'Give this act a name that captures its role in the story.',
                        )}
                    </span>
                </FormField>

                {/* Description */}
                <FormField label={t('act.description', 'Description')}>
                    <Textarea
                        value={description}
                        onChange={(e) =>
                            handleDescriptionChange(e.target.value)
                        }
                        rows={3}
                    />
                    <span className="text-[10px] text-ink-faint italic">
                        {t(
                            'act.descriptionHelper',
                            "Optional. Notes about this act's purpose in the overall arc.",
                        )}
                    </span>
                </FormField>

                {/* Divider */}
                <div className="h-px bg-border" />

                {/* Summary */}
                <div className="flex flex-col gap-2.5">
                    <SectionLabel>{t('act.summary', 'Summary')}</SectionLabel>
                    <div className="flex flex-col gap-1.5">
                        <div className="flex items-center justify-between">
                            <span className="text-[12px] text-ink-soft">
                                {t('act.plotPoints', 'Plot points')}
                            </span>
                            <span className="text-[12px] font-semibold text-ink">
                                {summary.plotPointCount}
                            </span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-[12px] text-ink-soft">
                                {t('act.beats', 'Beats')}
                            </span>
                            <span className="text-[12px] font-semibold text-ink">
                                {summary.beatCount}
                            </span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-[12px] text-ink-soft">
                                {t('act.fulfilled', 'Fulfilled')}
                            </span>
                            <span className="text-[12px] font-semibold text-[#5A8F5C]">
                                {summary.fulfilledCount} of {summary.beatCount}
                            </span>
                        </div>
                    </div>
                    <span className="text-[10px] text-ink-faint italic">
                        {t(
                            'act.summaryHelper',
                            'A snapshot of progress within this act.',
                        )}
                    </span>
                </div>

                {/* Spacer */}
                <div className="flex-1" />

                {/* Delete */}
                <Button
                    variant="danger"
                    onClick={() => onDelete(act.id)}
                    className="w-full py-2.5"
                >
                    <Trash2 size={14} />
                    {t('act.deleteAct')}
                </Button>
            </div>
        </Drawer>
    );
}
