import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

export type ReviewChapter = {
    number: number;
    title: string;
    wordCount: number;
    content: string;
    included: boolean;
};

export default function ReviewChapterRow({
    chapter,
    onToggle,
    onTitleChange,
}: {
    chapter: ReviewChapter;
    onToggle: () => void;
    onTitleChange: (title: string) => void;
}) {
    const { i18n } = useTranslation('onboarding');
    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState(chapter.title);
    const inputRef = useRef<HTMLInputElement>(null);

    function commitEdit() {
        const trimmed = draft.trim();
        if (trimmed && trimmed !== chapter.title) {
            onTitleChange(trimmed);
        } else {
            setDraft(chapter.title);
        }
        setEditing(false);
    }

    return (
        <div
            className={`flex items-center gap-3 border-b border-border-light py-2.5 ${
                !chapter.included ? 'opacity-45' : ''
            }`}
        >
            <button
                type="button"
                onClick={onToggle}
                className="flex h-4 w-4 shrink-0 items-center justify-center rounded-[3px] border-[1.5px] transition-colors"
                style={{
                    borderColor: chapter.included ? '#141414' : '#C5C1B8',
                    backgroundColor: chapter.included ? '#141414' : 'transparent',
                }}
            >
                {chapter.included && (
                    <svg width="10" height="8" viewBox="0 0 10 8" fill="none">
                        <path
                            d="M1 4L3.5 6.5L9 1"
                            stroke="#FCFAF7"
                            strokeWidth="1.5"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        />
                    </svg>
                )}
            </button>

            <span className="w-5 shrink-0 text-right text-[13px] leading-4 text-ink-faint">
                {chapter.number}
            </span>

            {editing ? (
                <input
                    ref={inputRef}
                    type="text"
                    value={draft}
                    onChange={(e) => setDraft(e.target.value)}
                    onBlur={commitEdit}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') commitEdit();
                        if (e.key === 'Escape') {
                            setDraft(chapter.title);
                            setEditing(false);
                        }
                    }}
                    className="min-w-0 flex-1 border-b border-dashed border-border-dashed bg-transparent text-sm leading-[18px] text-ink outline-none"
                />
            ) : (
                <button
                    type="button"
                    onClick={() => {
                        if (!chapter.included) return;
                        setEditing(true);
                        requestAnimationFrame(() => inputRef.current?.focus());
                    }}
                    className="min-w-0 flex-1 text-left text-sm leading-[18px] text-ink hover:border-b hover:border-dashed hover:border-border-dashed"
                >
                    {chapter.title}
                </button>
            )}

            <span className="shrink-0 text-xs leading-4 text-ink-faint">
                {chapter.wordCount.toLocaleString(i18n.language)}
            </span>
        </div>
    );
}
