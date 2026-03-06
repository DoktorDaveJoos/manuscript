import { update as updateWritingGoal } from '@/actions/App/Http/Controllers/WritingGoalController';
import { jsonFetchHeaders } from '@/lib/utils';
import type { WritingGoalData } from '@/types/models';
import { useCallback, useState } from 'react';

export default function WritingGoal({
    bookId,
    writingGoal,
}: {
    bookId: number;
    writingGoal: WritingGoalData;
}) {
    const [goal, setGoal] = useState(writingGoal.daily_word_count_goal);
    const [isEditing, setIsEditing] = useState(false);
    const [inputValue, setInputValue] = useState(String(goal ?? 500));

    const handleSave = useCallback(async () => {
        const parsed = parseInt(inputValue, 10);
        if (isNaN(parsed) || parsed < 50 || parsed > 50000) return;

        try {
            const response = await fetch(updateWritingGoal.url(bookId), {
                method: 'PUT',
                headers: jsonFetchHeaders(),
                body: JSON.stringify({ daily_word_count_goal: parsed }),
            });

            if (!response.ok) return;

            setGoal(parsed);
            setIsEditing(false);
        } catch {
            // Ignore errors
        }
    }, [bookId, inputValue]);

    const handleKeyDown = useCallback(
        (e: React.KeyboardEvent) => {
            if (e.key === 'Enter') {
                handleSave();
            } else if (e.key === 'Escape') {
                setIsEditing(false);
                setInputValue(String(goal ?? 500));
            }
        },
        [handleSave, goal],
    );

    // No goal set: show setup card
    if (!goal && !isEditing) {
        return (
            <div className="rounded-lg bg-surface-card px-5 py-4">
                <p className="text-sm text-ink-muted">Set a daily writing goal to track your progress and build a streak.</p>
                <button
                    type="button"
                    onClick={() => setIsEditing(true)}
                    className="mt-3 rounded-md bg-ink px-3 py-1.5 text-xs font-medium text-surface transition-colors hover:bg-ink/90"
                >
                    Set writing goal
                </button>
            </div>
        );
    }

    // Editing mode
    if (isEditing) {
        return (
            <div className="rounded-lg bg-surface-card px-5 py-4">
                <label className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-faint">
                    Daily word goal
                </label>
                <div className="mt-2 flex items-center gap-2">
                    <input
                        type="number"
                        min={50}
                        max={50000}
                        value={inputValue}
                        onChange={(e) => setInputValue(e.target.value)}
                        onKeyDown={handleKeyDown}
                        className="w-28 rounded-md border border-border bg-surface px-3 py-1.5 text-sm text-ink focus:border-ink focus:ring-0 focus:outline-none"
                        autoFocus
                    />
                    <span className="text-xs text-ink-faint">words per day</span>
                    <div className="flex-1" />
                    <button
                        type="button"
                        onClick={() => {
                            setIsEditing(false);
                            setInputValue(String(goal ?? 500));
                        }}
                        className="rounded-md px-3 py-1.5 text-xs text-ink-muted transition-colors hover:bg-neutral-bg"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={handleSave}
                        className="rounded-md bg-ink px-3 py-1.5 text-xs font-medium text-surface transition-colors hover:bg-ink/90"
                    >
                        Save
                    </button>
                </div>
            </div>
        );
    }

    // Goal display
    const progress = goal > 0 ? Math.min(100, Math.round((writingGoal.today_words / goal) * 100)) : 0;

    return (
        <div className="rounded-lg bg-surface-card px-5 py-4">
            <div className="flex items-center justify-between">
                <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-faint">
                    Today&apos;s progress
                </span>
                <button
                    type="button"
                    onClick={() => setIsEditing(true)}
                    className="text-xs text-ink-faint transition-colors hover:text-ink"
                >
                    Edit goal
                </button>
            </div>

            {/* Progress bar */}
            <div className="mt-3 h-2 overflow-hidden rounded-full bg-neutral-bg">
                <div
                    className={`h-full rounded-full transition-all duration-500 ${
                        writingGoal.goal_met_today ? 'bg-status-final' : 'bg-ink'
                    }`}
                    style={{ width: `${progress}%` }}
                />
            </div>

            <div className="mt-2 flex items-center justify-between">
                <span className="text-sm text-ink">
                    {writingGoal.today_words.toLocaleString('en-US')}{' '}
                    <span className="text-ink-muted">/ {goal.toLocaleString('en-US')} words</span>
                    {writingGoal.goal_met_today && (
                        <svg
                            className="ml-1.5 inline-block h-4 w-4 text-status-final"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            strokeWidth={2}
                        >
                            <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    )}
                </span>

                {writingGoal.streak > 0 && (
                    <span className="flex items-center gap-1 text-sm text-ink-muted" title="Writing streak">
                        <span className="text-base">🔥</span>
                        {writingGoal.streak} day{writingGoal.streak !== 1 ? 's' : ''}
                    </span>
                )}
            </div>
        </div>
    );
}
