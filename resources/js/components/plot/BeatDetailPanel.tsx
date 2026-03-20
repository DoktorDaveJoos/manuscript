import { router } from '@inertiajs/react';
import { FileText, Plus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import FormField from '@/components/ui/FormField';
import Input from '@/components/ui/Input';
import PanelHeader from '@/components/ui/PanelHeader';
import SectionLabel from '@/components/ui/SectionLabel';
import Select from '@/components/ui/Select';
import Textarea from '@/components/ui/Textarea';
import type { Beat, BeatStatus } from '@/types/models';

const STATUS_OPTIONS: BeatStatus[] = ['planned', 'fulfilled', 'abandoned'];

type BeatDetailPanelProps = {
    beat: Beat;
    bookId: number;
    onClose: () => void;
};

export default function BeatDetailPanel({
    beat,
    bookId,
    onClose,
}: BeatDetailPanelProps) {
    const { t } = useTranslation('plot');
    const [title, setTitle] = useState(beat.title);
    const [description, setDescription] = useState(beat.description ?? '');

    const handleTitleBlur = () => {
        if (title !== beat.title) {
            router.patch(
                `/books/${bookId}/beats/${beat.id}`,
                { title },
                { preserveScroll: true },
            );
        }
    };

    const handleDescriptionBlur = () => {
        if (description !== (beat.description ?? '')) {
            router.patch(
                `/books/${bookId}/beats/${beat.id}`,
                { description },
                { preserveScroll: true },
            );
        }
    };

    const handleStatusChange = (status: string) => {
        router.patch(
            `/books/${bookId}/beats/${beat.id}/status`,
            { status },
            { preserveScroll: true },
        );
    };

    const plotPointType = beat.plot_point?.type;

    return (
        <aside className="flex h-full w-[320px] shrink-0 flex-col border-l border-border bg-surface-card">
            <PanelHeader title={t('beat.details')} onClose={onClose} />

            <div className="flex flex-1 flex-col gap-5 overflow-y-auto p-5">
                {/* Title */}
                <FormField label={t('beat.title')} className="gap-1.5">
                    <Input
                        type="text"
                        value={title}
                        onChange={(e) => setTitle(e.target.value)}
                        onBlur={handleTitleBlur}
                    />
                </FormField>

                {/* Description */}
                <FormField label={t('beat.description')} className="gap-1.5">
                    <Textarea
                        value={description}
                        onChange={(e) => setDescription(e.target.value)}
                        onBlur={handleDescriptionBlur}
                        rows={4}
                        placeholder={t('beat.descriptionPlaceholder')}
                    />
                </FormField>

                {/* Status + Type row */}
                <div className="flex gap-3">
                    <FormField
                        label={t('beat.status')}
                        className="flex-1 gap-1.5"
                    >
                        <Select
                            value={beat.status}
                            onChange={(e) => handleStatusChange(e.target.value)}
                        >
                            {STATUS_OPTIONS.map((value) => (
                                <option key={value} value={value}>
                                    {t(`status.${value}`)}
                                </option>
                            ))}
                        </Select>
                    </FormField>

                    <FormField
                        label={t('beat.type')}
                        className="flex-1 gap-1.5"
                    >
                        <div className="flex items-center rounded-md border border-border bg-surface px-3 py-2 text-[13px] text-ink-soft">
                            {plotPointType ? t(`type.${plotPointType}`) : '—'}
                        </div>
                    </FormField>
                </div>

                {/* Divider */}
                <div className="h-px bg-border" />

                {/* Linked Chapters */}
                <div className="flex flex-col gap-2.5">
                    <div className="flex items-center justify-between">
                        <SectionLabel>{t('beat.linkedChapters')}</SectionLabel>
                        <button
                            type="button"
                            className="flex items-center justify-center text-ink-muted transition-colors hover:text-ink-soft"
                            aria-label={t('beat.addChapter')}
                        >
                            <Plus size={14} />
                        </button>
                    </div>

                    {beat.chapters && beat.chapters.length > 0 ? (
                        <div className="flex flex-col gap-2.5">
                            {beat.chapters.map((chapter) => (
                                <div
                                    key={chapter.id}
                                    className="flex items-center gap-2 rounded-md bg-neutral-bg px-3 py-2"
                                >
                                    <FileText
                                        size={14}
                                        className="shrink-0 text-ink-muted"
                                    />
                                    <span className="text-[12px] font-medium text-ink">
                                        Ch {chapter.reader_order} —{' '}
                                        {chapter.title}
                                    </span>
                                </div>
                            ))}
                        </div>
                    ) : null}
                </div>
            </div>
        </aside>
    );
}
