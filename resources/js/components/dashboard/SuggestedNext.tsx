import type { SuggestedNext as SuggestedNextType } from '@/types/models';

export default function SuggestedNext({
    suggestion,
}: {
    suggestion: SuggestedNextType;
    bookId: number;
}) {
    return (
        <div className="flex items-start gap-4">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" className="shrink-0 stroke-accent">
                <circle cx="10" cy="10" r="9" strokeWidth="1.5" />
                <path d="M6 10l3 3 5-6" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
            </svg>

            <div className="flex flex-col gap-1">
                <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-accent">
                    Suggested Next
                </span>
                <h3 className="font-serif text-[20px] font-medium leading-[26px] text-ink">
                    {suggestion.title}
                </h3>
                <p className="text-[13px] leading-[20px] text-ink-muted">{suggestion.description}</p>
            </div>
        </div>
    );
}
