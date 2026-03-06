export default function ProFeatureLock({ children }: { children: React.ReactNode }) {
    return (
        <div className="relative">
            {children}
            <div className="absolute inset-0 z-10 flex flex-col items-center justify-center rounded-md bg-surface/80 backdrop-blur-[2px]">
                <svg width="20" height="20" viewBox="0 0 16 16" fill="none" className="mb-2 text-ink-faint">
                    <rect x="3" y="7" width="10" height="7" rx="1.5" stroke="currentColor" strokeWidth="1.5" />
                    <path d="M5 7V5a3 3 0 016 0v2" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                </svg>
                <span className="text-xs font-semibold uppercase tracking-[0.06em] text-ink-muted">
                    Manuscript Pro
                </span>
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
