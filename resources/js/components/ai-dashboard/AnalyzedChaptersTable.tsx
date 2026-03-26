import { Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { show as showChapter } from '@/actions/App/Http/Controllers/ChapterController';
import SectionLabel from '@/components/ui/SectionLabel';

type ChapterRow = {
    id: number;
    title: string;
    reader_order: number;
    score: number | null;
    word_count: number;
    estimated_pages: number;
    findings_count: number;
    storyline_name: string | null;
    is_analyzed: boolean;
};

type PaginatedChapters = {
    data: ChapterRow[];
    current_page: number;
    last_page: number;
    total: number;
};

export default function AnalyzedChaptersTable({
    bookId,
    chapters,
}: {
    bookId: number;
    chapters: PaginatedChapters;
}) {
    const { t } = useTranslation('ai-dashboard');

    const from = (chapters.current_page - 1) * 5 + 1;
    const to = Math.min(chapters.current_page * 5, chapters.total);

    const goToPage = (page: number) => {
        router.visit(window.location.pathname + `?page=${page}`, {
            preserveState: true,
            preserveScroll: true,
            only: ['analyzed_chapters'],
        });
    };

    return (
        <div className="flex flex-col gap-3">
            <SectionLabel>{t('chapters.label')}</SectionLabel>
            <div className="overflow-hidden rounded-lg bg-surface-card">
                <table className="w-full">
                    <thead>
                        <tr className="h-10 bg-neutral-bg">
                            <th className="px-3 text-left text-[11px] font-medium tracking-[0.08em] text-ink-muted uppercase">
                                {t('chapters.column.chapter')}
                            </th>
                            <th className="px-3 text-left text-[11px] font-medium tracking-[0.08em] text-ink-muted uppercase">
                                {t('chapters.column.score')}
                            </th>
                            <th className="px-3 text-left text-[11px] font-medium tracking-[0.08em] text-ink-muted uppercase">
                                {t('chapters.column.pages')}
                            </th>
                            <th className="px-3 text-left text-[11px] font-medium tracking-[0.08em] text-ink-muted uppercase">
                                {t('chapters.column.findings')}
                            </th>
                            <th className="px-3 text-left text-[11px] font-medium tracking-[0.08em] text-ink-muted uppercase">
                                {t('chapters.column.storyline')}
                            </th>
                            <th className="px-3 text-left text-[11px] font-medium tracking-[0.08em] text-ink-muted uppercase">
                                {t('chapters.column.actions')}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {chapters.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={6}
                                    className="px-3 py-6 text-center text-[13px] text-ink-muted"
                                >
                                    {t('chapters.noChapters')}
                                </td>
                            </tr>
                        )}
                        {chapters.data.map((ch) => (
                            <tr
                                key={ch.id}
                                className="h-11 border-t border-border-subtle"
                            >
                                <td className="px-3 text-[13px] text-ink">
                                    <span className="mr-2 text-ink-faint">
                                        {ch.reader_order}.
                                    </span>
                                    {ch.title}
                                </td>
                                <td className="px-3 text-[13px] text-ink">
                                    {ch.is_analyzed
                                        ? ch.score
                                        : t('chapters.notAnalyzed')}
                                </td>
                                <td className="px-3 text-[13px] text-ink-muted">
                                    {ch.estimated_pages}
                                </td>
                                <td className="px-3 text-[13px] text-ink-muted">
                                    {ch.is_analyzed
                                        ? ch.findings_count
                                        : t('chapters.notAnalyzed')}
                                </td>
                                <td className="px-3 text-[13px] text-ink-muted">
                                    {ch.storyline_name ?? '—'}
                                </td>
                                <td className="px-3 text-[13px]">
                                    <Link
                                        href={showChapter.url({
                                            book: bookId,
                                            chapter: ch.id,
                                        })}
                                        className="text-accent transition-colors hover:text-accent/80"
                                    >
                                        {t('chapters.view')}
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {/* Pagination */}
                {chapters.total > 5 && (
                    <div className="flex items-center justify-between border-t border-border-subtle px-3 py-2">
                        <span className="text-[12px] text-ink-muted">
                            {t('chapters.pagination.showing', {
                                from,
                                to,
                                total: chapters.total,
                            })}
                        </span>
                        <div className="flex items-center gap-1">
                            <button
                                type="button"
                                onClick={() =>
                                    goToPage(chapters.current_page - 1)
                                }
                                disabled={chapters.current_page <= 1}
                                className="rounded p-1 text-ink-muted transition-colors hover:text-ink disabled:opacity-30"
                            >
                                <ChevronLeft size={16} />
                            </button>
                            <button
                                type="button"
                                onClick={() =>
                                    goToPage(chapters.current_page + 1)
                                }
                                disabled={
                                    chapters.current_page >= chapters.last_page
                                }
                                className="rounded p-1 text-ink-muted transition-colors hover:text-ink disabled:opacity-30"
                            >
                                <ChevronRight size={16} />
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
