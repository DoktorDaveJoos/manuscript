import { Lock } from 'lucide-react';

type ProFeature =
    | 'plot'
    | 'export'
    | 'book'
    | 'storyline'
    | 'wiki'
    | 'dashboard';

const featureMessages: Record<ProFeature, string> = {
    plot: 'Unlock the Plot Board',
    export: 'Publish-ready export',
    book: 'Create unlimited books',
    storyline: 'Add unlimited storylines',
    wiki: 'Unlimited Story Bible entries',
    dashboard: 'Advanced writing analytics',
};

export default function ProFeatureLock({
    children,
    feature,
    usage,
}: {
    children: React.ReactNode;
    feature?: ProFeature;
    usage?: { count: number; limit: number } | null;
}) {
    const message = feature ? featureMessages[feature] : undefined;

    return (
        <div className="relative">
            {children}
            <div className="absolute inset-0 z-10 flex flex-col items-center justify-center rounded-md bg-surface/80 backdrop-blur-[2px]">
                <Lock size={20} className="mb-2 text-ink-faint" />
                <span className="text-xs font-semibold uppercase tracking-[0.08em] text-ink-muted">
                    Manuscript Pro
                </span>
                {message && (
                    <span className="mt-1 text-[11px] text-ink-faint">
                        {message}
                    </span>
                )}
                {usage && (
                    <span className="mt-0.5 text-[11px] text-ink-faint">
                        {usage.count}/{usage.limit} used
                    </span>
                )}
                <a
                    href="https://getmanuscript.app"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="mt-1 text-[11px] text-ink-faint underline decoration-ink-faint/40 transition-colors hover:text-ink-muted"
                >
                    Get your licence
                </a>
            </div>
        </div>
    );
}
