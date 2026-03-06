import { Lock } from '@phosphor-icons/react';

export default function ProFeatureLock({ children }: { children: React.ReactNode }) {
    return (
        <div className="relative">
            {children}
            <div className="absolute inset-0 z-10 flex flex-col items-center justify-center rounded-md bg-surface/80 backdrop-blur-[2px]">
                <Lock size={20} className="mb-2 text-ink-faint" />
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
