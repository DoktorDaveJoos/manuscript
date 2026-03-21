import { router } from '@inertiajs/react';
import { FileText, Plus, Search, X } from 'lucide-react';
import { useCallback, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import FormField from '@/components/ui/FormField';
import Input from '@/components/ui/Input';
import PanelHeader from '@/components/ui/PanelHeader';
import SectionLabel from '@/components/ui/SectionLabel';
import Select from '@/components/ui/Select';
import Textarea from '@/components/ui/Textarea';
import type { Beat, BeatStatus, Storyline } from '@/types/models';

const STATUS_OPTIONS: BeatStatus[] = ['planned', 'fulfilled', 'abandoned'];

type ChapterSummary = {
    id: number;
    title: string;
    storyline_id: number;
    reader_order: number;
    storyline?: { id: number; name: string };
};

type BeatDetailPanelProps = {
    beat: Beat;
    bookId: number;
    chapters: ChapterSummary[];
    storylines: Storyline[];
    onClose: () => void;
};

export default function BeatDetailPanel({
    beat,
    bookId,
    chapters,
    storylines,
    onClose,
}: BeatDetailPanelProps) {
    const { t } = useTranslation('plot');
    const [title, setTitle] = useState(beat.title);
    const [description, setDescription] = useState(beat.description ?? '');
    const [showChapterPicker, setShowChapterPicker] = useState(false);
    const [chapterSearch, setChapterSearch] = useState('');
    const [storylineFilter, setStorylineFilter] = useState<number | 'all'>(
        'all',
    );
    const searchInputRef = useRef<HTMLInputElement>(null);

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

    const linkedChapterIds = useMemo(
        () => new Set((beat.chapters ?? []).map((ch) => ch.id)),
        [beat.chapters],
    );

    const availableChapters = useMemo(() => {
        return chapters.filter((ch) => {
            if (linkedChapterIds.has(ch.id)) {
                return false;
            }
            if (
                storylineFilter !== 'all' &&
                ch.storyline_id !== storylineFilter
            ) {
                return false;
            }
            if (chapterSearch.trim()) {
                const q = chapterSearch.toLowerCase();
                return (
                    ch.title.toLowerCase().includes(q) ||
                    String(ch.reader_order).includes(q)
                );
            }
            return true;
        });
    }, [chapters, linkedChapterIds, storylineFilter, chapterSearch]);

    const handleLinkChapter = useCallback(
        (chapterId: number) => {
            router.post(
                `/books/${bookId}/beats/${beat.id}/chapters`,
                { chapter_id: chapterId },
                { preserveScroll: true },
            );
            setShowChapterPicker(false);
            setChapterSearch('');
            setStorylineFilter('all');
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

    const handleOpenPicker = useCallback(() => {
        setShowChapterPicker(true);
        setTimeout(() => searchInputRef.current?.focus(), 0);
    }, []);

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
                            onClick={handleOpenPicker}
                            className="flex items-center justify-center text-ink-muted transition-colors hover:text-ink-soft"
                            aria-label={t('beat.addChapter')}
                        >
                            <Plus size={14} />
                        </button>
                    </div>

                    {beat.chapters && beat.chapters.length > 0 ? (
                        <div className="flex flex-col gap-1.5">
                            {beat.chapters.map((chapter) => (
                                <div
                                    key={chapter.id}
                                    className="group flex items-center gap-2 rounded-md bg-neutral-bg px-3 py-2"
                                >
                                    <FileText
                                        size={14}
                                        className="shrink-0 text-ink-muted"
                                    />
                                    <span className="min-w-0 flex-1 truncate text-[12px] font-medium text-ink">
                                        Ch {chapter.reader_order} —{' '}
                                        {chapter.title}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            handleUnlinkChapter(chapter.id)
                                        }
                                        className="shrink-0 opacity-0 transition-opacity group-hover:opacity-100"
                                        aria-label={t('beat.unlinkChapter')}
                                    >
                                        <X
                                            size={12}
                                            className="text-ink-muted hover:text-ink"
                                        />
                                    </button>
                                </div>
                            ))}
                        </div>
                    ) : null}

                    {/* Chapter picker dropdown */}
                    {showChapterPicker && (
                        <div className="flex flex-col gap-2 rounded-lg border border-border bg-surface p-2.5">
                            {/* Search */}
                            <div className="relative">
                                <Search
                                    size={13}
                                    className="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-ink-faint"
                                />
                                <input
                                    ref={searchInputRef}
                                    type="text"
                                    value={chapterSearch}
                                    onChange={(e) =>
                                        setChapterSearch(e.target.value)
                                    }
                                    placeholder={t(
                                        'beat.chapterSearchPlaceholder',
                                    )}
                                    className="w-full rounded-md border border-border bg-surface py-1.5 pr-3 pl-8 text-[12px] text-ink placeholder:text-ink-faint focus:ring-1 focus:ring-accent focus:outline-none"
                                />
                            </div>

                            {/* Storyline filter */}
                            {storylines.length > 1 && (
                                <Select
                                    variant="compact"
                                    value={
                                        storylineFilter === 'all'
                                            ? ''
                                            : String(storylineFilter)
                                    }
                                    onChange={(e) =>
                                        setStorylineFilter(
                                            e.target.value === ''
                                                ? 'all'
                                                : Number(e.target.value),
                                        )
                                    }
                                >
                                    <option value="">
                                        {t('beat.allStorylines')}
                                    </option>
                                    {storylines.map((s) => (
                                        <option key={s.id} value={String(s.id)}>
                                            {s.name}
                                        </option>
                                    ))}
                                </Select>
                            )}

                            {/* Results */}
                            <div className="max-h-[160px] overflow-y-auto">
                                {availableChapters.length > 0 ? (
                                    <div className="flex flex-col gap-0.5">
                                        {availableChapters.map((ch) => (
                                            <button
                                                key={ch.id}
                                                type="button"
                                                onClick={() =>
                                                    handleLinkChapter(ch.id)
                                                }
                                                className="flex items-center gap-2 rounded-md px-2.5 py-1.5 text-left text-[12px] text-ink transition-colors hover:bg-neutral-bg"
                                            >
                                                <FileText
                                                    size={13}
                                                    className="shrink-0 text-ink-muted"
                                                />
                                                <span className="min-w-0 flex-1 truncate">
                                                    Ch {ch.reader_order} —{' '}
                                                    {ch.title}
                                                </span>
                                                {ch.storyline && (
                                                    <span className="shrink-0 text-[11px] text-ink-faint">
                                                        {ch.storyline.name}
                                                    </span>
                                                )}
                                            </button>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="px-2.5 py-2 text-[12px] text-ink-faint">
                                        {t('beat.noChaptersAvailable')}
                                    </p>
                                )}
                            </div>

                            {/* Cancel */}
                            <button
                                type="button"
                                onClick={() => {
                                    setShowChapterPicker(false);
                                    setChapterSearch('');
                                    setStorylineFilter('all');
                                }}
                                className="self-end text-[11px] text-ink-muted transition-colors hover:text-ink-soft"
                            >
                                {t('beat.cancelChapterPicker')}
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </aside>
    );
}
