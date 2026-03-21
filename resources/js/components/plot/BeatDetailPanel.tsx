import { router } from '@inertiajs/react';
import { FileText, Plus, Trash2, X } from 'lucide-react';
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
import { STATUS_PILL_OPTIONS } from '@/lib/plot-constants';
import type { Beat, BeatStatus } from '@/types/models';
import StatusPillGroup from './StatusPillGroup';

type BeatDetailPanelProps = {
    beat: Beat;
    bookId: number;
    chapters?: { id: number; title: string; reader_order: number }[];
    onClose: () => void;
    onDelete?: (beatId: number) => void;
    onTitleChange?: (title: string) => void;
};

export default function BeatDetailPanel({
    beat,
    bookId,
    chapters = [],
    onClose,
    onDelete,
    onTitleChange,
}: BeatDetailPanelProps) {
    const { t } = useTranslation('plot');
    const titleRef = useRef<HTMLInputElement>(null);
    const [title, setTitle] = useState(beat.title);
    const [description, setDescription] = useState(beat.description ?? '');
    const [chapterDropdownOpen, setChapterDropdownOpen] = useState(false);
    const chapterDropdownRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        titleRef.current?.focus();
        titleRef.current?.select();
    }, []);

    useEffect(() => {
        if (!chapterDropdownOpen) return;

        function handleClickOutside(e: MouseEvent) {
            if (
                chapterDropdownRef.current &&
                !chapterDropdownRef.current.contains(e.target as Node)
            ) {
                setChapterDropdownOpen(false);
            }
        }

        document.addEventListener('mousedown', handleClickOutside);
        return () =>
            document.removeEventListener('mousedown', handleClickOutside);
    }, [chapterDropdownOpen]);

    const patchBeat = useCallback(
        (data: Record<string, string | number>) => {
            router.patch(`/books/${bookId}/beats/${beat.id}`, data, {
                preserveScroll: true,
            });
        },
        [bookId, beat.id],
    );

    const debouncedPatchTitle = useDebouncedCallback(
        (title: string) => patchBeat({ title }),
        500,
    );

    const debouncedPatchDescription = useDebouncedCallback(
        (description: string) => patchBeat({ description }),
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

    const handleStatusChange = useCallback(
        (status: BeatStatus) => {
            router.patch(
                `/books/${bookId}/beats/${beat.id}/status`,
                { status },
                { preserveScroll: true },
            );
        },
        [bookId, beat.id],
    );

    const linkedChapterIds = useMemo(
        () => new Set((beat.chapters ?? []).map((c) => c.id)),
        [beat.chapters],
    );

    const availableChapters = useMemo(
        () => chapters.filter((c) => !linkedChapterIds.has(c.id)),
        [chapters, linkedChapterIds],
    );

    const handleLinkChapter = useCallback(
        (chapterId: number) => {
            router.post(
                `/books/${bookId}/beats/${beat.id}/chapters`,
                { chapter_id: chapterId },
                { preserveScroll: true },
            );
            setChapterDropdownOpen(false);
        },
        [bookId, beat.id],
    );

    const handleUnlinkChapter = useCallback(
        (chapterId: number) => {
            router.delete(
                `/books/${bookId}/beats/${beat.id}/chapters/${chapterId}`,
                { preserveScroll: true },
            );
        },
        [bookId, beat.id],
    );

    const plotPointType = beat.plot_point?.type;

    return (
        <Drawer onClose={onClose}>
            <PanelHeader title={t('beat.details')} onClose={onClose} />

            <div className="flex flex-1 flex-col gap-5 overflow-y-auto p-5">
                {/* Title */}
                <FormField label={t('beat.title')}>
                    <Input
                        ref={titleRef}
                        type="text"
                        value={title}
                        onChange={(e) => handleTitleChange(e.target.value)}
                    />
                </FormField>

                {/* Description */}
                <FormField label={t('beat.description')}>
                    <Textarea
                        value={description}
                        onChange={(e) =>
                            handleDescriptionChange(e.target.value)
                        }
                        rows={4}
                        placeholder={t('beat.descriptionPlaceholder')}
                    />
                    <span className="text-[10px] text-ink-faint italic">
                        {t(
                            'beat.descriptionHelper',
                            'What happens during this beat?',
                        )}
                    </span>
                </FormField>

                {/* Status */}
                <FormField label={t('beat.status')}>
                    <StatusPillGroup
                        options={STATUS_PILL_OPTIONS}
                        value={beat.status}
                        onChange={handleStatusChange}
                    />
                </FormField>

                {/* Type (read-only, inherited from plot point) */}
                <FormField label={t('beat.type')}>
                    <div className="flex items-center rounded-md border border-border bg-surface px-3 py-2 text-[13px] text-ink-soft">
                        {plotPointType ? t(`type.${plotPointType}`) : '—'}
                    </div>
                    <span className="text-[10px] text-ink-faint italic">
                        {t(
                            'beat.typeHelper',
                            'Inherited from the parent plot point.',
                        )}
                    </span>
                </FormField>

                {/* Divider */}
                <div className="h-px bg-border" />

                {/* Linked Chapters */}
                <div className="flex flex-col gap-2.5">
                    <div className="flex items-center justify-between">
                        <SectionLabel>{t('beat.linkedChapters')}</SectionLabel>
                        <div ref={chapterDropdownRef} className="relative">
                            <button
                                type="button"
                                onClick={() =>
                                    setChapterDropdownOpen(!chapterDropdownOpen)
                                }
                                className="flex items-center justify-center text-ink-muted transition-colors hover:text-ink-soft"
                                aria-label={t('beat.addChapter')}
                            >
                                <Plus size={14} />
                            </button>
                            {chapterDropdownOpen &&
                                availableChapters.length > 0 && (
                                    <div className="absolute top-full right-0 z-50 mt-1 max-h-[200px] w-[220px] overflow-y-auto rounded-lg bg-surface-card shadow-[0_4px_24px_#0000001F,0_0_0_1px_#0000000A]">
                                        <div className="flex flex-col p-1">
                                            {availableChapters.map(
                                                (chapter) => (
                                                    <button
                                                        key={chapter.id}
                                                        type="button"
                                                        onClick={() =>
                                                            handleLinkChapter(
                                                                chapter.id,
                                                            )
                                                        }
                                                        className="flex w-full items-center gap-2 rounded-[5px] px-3 py-2 text-left text-[12px] font-medium text-ink transition-colors hover:bg-neutral-bg"
                                                    >
                                                        <FileText
                                                            size={12}
                                                            className="shrink-0 text-ink-muted"
                                                        />
                                                        Ch{' '}
                                                        {chapter.reader_order} —{' '}
                                                        {chapter.title}
                                                    </button>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                )}
                        </div>
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
                                    <span className="flex-1 text-[12px] font-medium text-ink">
                                        Ch {chapter.reader_order} —{' '}
                                        {chapter.title}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            handleUnlinkChapter(chapter.id)
                                        }
                                        className="shrink-0 text-ink-muted transition-colors hover:text-delete"
                                        aria-label={t('beat.unlinkChapter')}
                                    >
                                        <X size={12} />
                                    </button>
                                </div>
                            ))}
                        </div>
                    ) : null}
                    <span className="text-[10px] text-ink-faint italic">
                        {t(
                            'beat.chapterHelper',
                            'Connect this beat to the chapters where it plays out.',
                        )}
                    </span>
                </div>

                {/* Spacer */}
                <div className="flex-1" />

                {/* Delete */}
                {onDelete && (
                    <Button
                        variant="danger"
                        onClick={() => onDelete(beat.id)}
                        className="w-full py-2.5"
                    >
                        <Trash2 size={14} />
                        {t('beat.deleteBeat')}
                    </Button>
                )}
            </div>
        </Drawer>
    );
}
