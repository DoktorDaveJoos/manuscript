function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    return `${Math.round(bytes / 1024)} KB`;
}

export default function FileRow({
    file,
    onRemove,
}: {
    file: File;
    onRemove: () => void;
}) {
    return (
        <div className="flex items-center gap-3.5 border-b border-border-light py-3.5">
            <svg
                width="18"
                height="18"
                viewBox="0 0 18 18"
                fill="none"
                className="shrink-0 text-ink-faint"
            >
                <rect
                    x="3"
                    y="2"
                    width="12"
                    height="14"
                    rx="1.5"
                    stroke="currentColor"
                    strokeWidth="1.2"
                />
                <path
                    d="M6 6h6M6 9h6M6 12h3"
                    stroke="currentColor"
                    strokeWidth="1.2"
                    strokeLinecap="round"
                />
            </svg>

            <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                <span className="truncate text-sm leading-[18px] text-ink">
                    {file.name}
                </span>
                <span className="text-[13px] leading-[18px] text-ink-faint">
                    {formatSize(file.size)}
                </span>
            </div>

            <button
                type="button"
                onClick={onRemove}
                className="shrink-0 text-ink-faint hover:text-ink-muted"
            >
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <path
                        d="M4 4l8 8M12 4l-8 8"
                        stroke="currentColor"
                        strokeWidth="1.2"
                        strokeLinecap="round"
                    />
                </svg>
            </button>
        </div>
    );
}
