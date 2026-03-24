import { router } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import DropZone from '@/components/onboarding/DropZone';
import FileRow from '@/components/onboarding/FileRow';
import ImportChapterRow from '@/components/onboarding/ImportChapterRow';
import type { ChapterItem } from '@/components/onboarding/ImportChapterRow';
import ReviewPhase from '@/components/onboarding/ReviewPhase';
import type { ReviewStoryline } from '@/components/onboarding/ReviewPhase';
import Button from '@/components/ui/Button';
import OnboardingLayout from '@/layouts/OnboardingLayout';
import type { Book, Storyline, StorylineType } from '@/types/models';
import {
    confirmImport,
    parse,
    skipImport,
} from '@/actions/App/Http/Controllers/BookController';
import { editor } from '@/actions/App/Http/Controllers/ChapterController';

function normalizeFilenameToStorylineName(filename: string): string {
    return filename
        .replace(/\.(docx|odt|txt|md|markdown)$/i, '')
        .replace(/[_-]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (c) => c.toUpperCase());
}

type FileEntry = {
    file: File;
    storylineName: string;
};

type ParsedChapter = {
    number: number;
    title: string;
    word_count: number;
    content: string;
};

type ParseResponse = {
    storylines: {
        storyline_name: string;
        storyline_type: StorylineType;
        chapters: ParsedChapter[];
    }[];
};

function UploadPhase({
    book,
    onStartParsing,
}: {
    book: Book & { storylines: Pick<Storyline, 'id' | 'book_id' | 'name'>[] };
    onStartParsing: (files: FileEntry[], mergeMode: boolean) => void;
}) {
    const { t } = useTranslation('onboarding');
    const [files, setFiles] = useState<FileEntry[]>([]);
    const [mergeMode, setMergeMode] = useState(false);

    function handleFiles(newFiles: File[]) {
        setFiles((prev) => [
            ...prev,
            ...newFiles.map((file) => ({
                file,
                storylineName: normalizeFilenameToStorylineName(file.name),
            })),
        ]);
    }

    return (
        <div className="flex flex-1 flex-col items-center gap-8 px-10 pt-20 pb-16">
            <div className="flex flex-col items-center gap-2">
                <h1 className="font-serif text-[32px] leading-10 font-normal tracking-[-0.01em] text-ink">
                    {book.title}
                </h1>
                <p className="text-sm leading-[18px] text-ink-muted">
                    {t('uploadPhase.subtitle')}
                </p>
            </div>

            <div className="flex w-[560px] flex-col gap-0">
                <DropZone onFiles={handleFiles} />

                {files.length > 0 && (
                    <div className="flex flex-col">
                        {files.map((entry, i) => (
                            <FileRow
                                key={`${entry.file.name}-${i}`}
                                file={entry.file}
                                onRemove={() =>
                                    setFiles((prev) =>
                                        prev.filter((_, j) => j !== i),
                                    )
                                }
                            />
                        ))}
                    </div>
                )}

                {files.length >= 2 && (
                    <label className="flex cursor-pointer items-center gap-3 px-4 py-3">
                        <input
                            type="checkbox"
                            checked={mergeMode}
                            onChange={(e) => setMergeMode(e.target.checked)}
                            className="h-4 w-4 rounded border-border text-ink accent-ink"
                        />
                        <span className="text-[13px] leading-4 text-ink-soft">
                            {t('uploadPhase.mergeFiles')}
                        </span>
                    </label>
                )}
            </div>

            <div className="w-[480px] pt-6">
                <p className="text-[11px] leading-[14px] font-medium tracking-[0.08em] text-ink-faint uppercase">
                    {t('importGuide.label')}
                </p>
                <div className="mt-5 flex flex-col">
                    {([1, 2, 3] as const).map((step, i) => (
                        <div
                            key={step}
                            className={`flex items-start gap-4 py-4 ${i < 2 ? 'border-b border-border-light' : ''}`}
                        >
                            <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-border bg-surface text-[11px] font-medium text-ink-muted">
                                {step}
                            </span>
                            <div className="flex flex-col gap-1">
                                <p className="text-sm leading-[18px] font-medium text-ink">
                                    {t(`importGuide.step${step}.title`)}
                                </p>
                                <p className="text-[13px] leading-5 text-ink-soft">
                                    {t(`importGuide.step${step}.description`)}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <div className="flex items-center gap-4 pt-4">
                <Button
                    variant="secondary"
                    size="lg"
                    type="button"
                    onClick={() => router.post(skipImport.url(book))}
                >
                    {t('uploadPhase.skip')}
                </Button>
                {files.length > 0 && (
                    <Button
                        variant="primary"
                        size="lg"
                        type="button"
                        onClick={() => onStartParsing(files, mergeMode)}
                    >
                        {t('uploadPhase.importFiles', { count: files.length })}
                    </Button>
                )}
            </div>
        </div>
    );
}

function ParsingPhase({
    book,
    chapters,
    onComplete,
}: {
    book: Book;
    chapters: ChapterItem[];
    onComplete: () => void;
}) {
    const { t } = useTranslation('onboarding');
    const [doneCount, setDoneCount] = useState(0);
    const total = chapters.length;

    useEffect(() => {
        if (doneCount >= total) {
            const timer = setTimeout(onComplete, 600);
            return () => clearTimeout(timer);
        }
        const timer = setTimeout(() => setDoneCount((c) => c + 1), 800);
        return () => clearTimeout(timer);
    }, [doneCount, total, onComplete]);

    const displayChapters: ChapterItem[] = chapters.map((ch, i) => ({
        ...ch,
        done: i < doneCount,
        wordCount: i < doneCount ? ch.wordCount : null,
    }));

    return (
        <div className="flex flex-1 flex-col items-center gap-10 px-10 pt-20 pb-16">
            <div className="flex flex-col items-center gap-2">
                <h1 className="font-serif text-[32px] leading-10 font-normal tracking-[-0.01em] text-ink">
                    {book.title}
                </h1>
                <p className="text-sm leading-[18px] text-ink-muted">
                    {t('parsingPhase.subtitle')}
                </p>
            </div>

            <div className="flex w-[400px] flex-col">
                {displayChapters.map((ch) => (
                    <ImportChapterRow key={ch.title} chapter={ch} />
                ))}
            </div>

            <span className="text-[13px] leading-4 text-ink-faint">
                {t('parsingPhase.progress', { done: doneCount, total })}
            </span>
        </div>
    );
}

export default function BooksImport({
    book,
}: {
    book: Book & { storylines: Pick<Storyline, 'id' | 'book_id' | 'name'>[] };
}) {
    const { t } = useTranslation('onboarding');
    const [phase, setPhase] = useState<'upload' | 'parsing' | 'review'>(
        'upload',
    );
    const [reviewData, setReviewData] = useState<ReviewStoryline[]>([]);
    const [parsingChapters, setParsingChapters] = useState<ChapterItem[]>([]);
    const [submitting, setSubmitting] = useState(false);

    async function handleStartParsing(files: FileEntry[], mergeMode: boolean) {
        const formData = new FormData();
        files.forEach((entry, i) => {
            formData.append(`files[${i}][file]`, entry.file);
            formData.append(`files[${i}][storyline_name]`, entry.storylineName);
            formData.append(`files[${i}][storyline_type]`, 'main');
        });

        if (mergeMode) {
            formData.append('merge_into_single_storyline', '1');
        }

        const allChapters: ChapterItem[] = [];

        try {
            const { data } = await axios.post<ParseResponse>(
                parse.url(book),
                formData,
            );

            const storylines: ReviewStoryline[] = data.storylines.map(
                (s, si) => {
                    const hasMultipleChapters =
                        s.chapters.length > 1 ||
                        s.chapters[0]?.title !== 'Full Document';

                    s.chapters.forEach((ch) => {
                        allChapters.push({
                            title: t('import.chapterLabel', {
                                number: ch.number,
                                title: ch.title,
                            }),
                            wordCount: ch.word_count,
                            done: false,
                        });
                    });

                    return {
                        name: s.storyline_name,
                        type: s.storyline_type,
                        filename: files[si]?.file.name ?? '',
                        chapters: s.chapters.map((ch) => ({
                            number: ch.number,
                            title: ch.title,
                            wordCount: ch.word_count,
                            content: ch.content,
                            included: ch.content.trim().length > 0,
                        })),
                        notice: !hasMultipleChapters
                            ? t('import.noHeadingsNotice')
                            : undefined,
                    };
                },
            );

            setReviewData(storylines);
            setParsingChapters(allChapters);
            setPhase('parsing');
        } catch {
            // Validation or server errors are handled by Inertia
        }
    }

    async function handleConfirm() {
        setSubmitting(true);

        const payload = {
            storylines: reviewData.map((s) => ({
                name: s.name,
                type: s.type,
                chapters: s.chapters.map((c) => ({
                    title: c.title,
                    content: c.content,
                    word_count: c.wordCount,
                    included: c.included,
                })),
            })),
        };

        try {
            await axios.post(confirmImport.url(book), payload);
            router.visit(editor.url(book));
        } catch {
            setSubmitting(false);
        }
    }

    return (
        <>
            {phase === 'upload' && (
                <UploadPhase book={book} onStartParsing={handleStartParsing} />
            )}
            {phase === 'parsing' && (
                <ParsingPhase
                    book={book}
                    chapters={parsingChapters}
                    onComplete={() => setPhase('review')}
                />
            )}
            {phase === 'review' && (
                <ReviewPhase
                    book={book}
                    storylines={reviewData}
                    onBack={() => setPhase('upload')}
                    onConfirm={handleConfirm}
                    onUpdate={setReviewData}
                    submitting={submitting}
                />
            )}
        </>
    );
}

BooksImport.layout = (page: React.ReactNode) => (
    <OnboardingLayout title="Import">{page}</OnboardingLayout>
);
