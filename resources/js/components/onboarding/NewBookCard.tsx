export default function NewBookCard({ onClick }: { onClick: () => void }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="flex w-[400px] min-h-[180px] shrink-0 cursor-pointer flex-col items-center justify-center gap-3 rounded-[10px] border-2 border-dashed border-border-dashed p-8 transition-colors hover:border-ink-faint"
        >
            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-neutral-bg">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                    <path d="M9 3.75v10.5M3.75 9h10.5" stroke="#8A8578" strokeWidth="1.5" strokeLinecap="round" />
                </svg>
            </div>
            <span className="text-sm leading-[18px] text-ink-muted">Create new book</span>
        </button>
    );
}
