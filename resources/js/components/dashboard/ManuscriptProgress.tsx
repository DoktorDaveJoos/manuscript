import type { ManuscriptTarget } from '@/types/models';

export default function ManuscriptProgress({ target }: { target: ManuscriptTarget }) {
    if (!target.target_word_count || target.milestone_reached) return null;

    return (
        <div>
            <div className="h-[3px] overflow-hidden rounded-full bg-neutral-bg">
                <div
                    className="h-full rounded-full bg-accent transition-all duration-700"
                    style={{ width: `${target.progress_percent}%` }}
                />
            </div>
            <p className="mt-2 font-serif text-[15px] text-ink-muted">
                {target.total_words.toLocaleString('en-US')} / {target.target_word_count.toLocaleString('en-US')}{' '}
                <span className="text-ink-faint">toward your first draft</span>
            </p>
        </div>
    );
}
