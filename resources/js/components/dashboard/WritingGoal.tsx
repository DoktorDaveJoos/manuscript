import { Flame } from '@phosphor-icons/react';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { jsonFetchHeaders } from '@/lib/utils';
import type { WritingGoalData } from '@/types/models';
import { update as updateWritingGoal } from '@/actions/App/Http/Controllers/WritingGoalController';

function FlameIcon({ className }: { className?: string }) {
    return <Flame size={16} weight="fill" className={className} />;
}

export default function WritingGoal({
    bookId,
    writingGoal,
    targetWordCount,
}: {
    bookId: number;
    writingGoal: WritingGoalData;
    targetWordCount?: number | null;
}) {
    const { t, i18n } = useTranslation('dashboard');
    const [goal, setGoal] = useState(writingGoal.daily_word_count_goal);
    const [isEditing, setIsEditing] = useState(false);
    const [inputValue, setInputValue] = useState(String(goal ?? 500));
    const [targetValue, setTargetValue] = useState(String(targetWordCount ?? ''));
    const [saveError, setSaveError] = useState(false);

    const handleSave = useCallback(async () => {
        const parsed = parseInt(inputValue, 10);
        if (isNaN(parsed) || parsed < 50 || parsed > 50000) return;

        const body: Record<string, unknown> = { daily_word_count_goal: parsed };
        const parsedTarget = targetValue ? parseInt(targetValue, 10) : null;
        if (parsedTarget !== null && (isNaN(parsedTarget) || parsedTarget < 1000 || parsedTarget > 500000)) {
            if (targetValue !== '') return;
        }
        body.target_word_count = parsedTarget;

        setSaveError(false);

        try {
            const response = await fetch(updateWritingGoal.url(bookId), {
                method: 'PUT',
                headers: jsonFetchHeaders(),
                body: JSON.stringify(body),
            });

            if (!response.ok) {
                setSaveError(true);
                return;
            }

            setGoal(parsed);
            setIsEditing(false);
        } catch {
            setSaveError(true);
        }
    }, [bookId, inputValue, targetValue]);

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
            <div className="rounded-lg bg-surface-card px-6 py-6">
                <p className="text-sm text-ink-muted">{t('writingGoal.setupPrompt')}</p>
                <button
                    type="button"
                    onClick={() => setIsEditing(true)}
                    className="mt-3 rounded-md bg-ink px-3 py-1.5 text-xs font-medium text-surface transition-colors hover:bg-ink/90"
                >
                    {t('writingGoal.setGoal')}
                </button>
            </div>
        );
    }

    // Editing mode
    if (isEditing) {
        return (
            <div className="rounded-lg bg-surface-card px-6 py-6">
                <label className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-faint">
                    {t('writingGoal.dailyWordGoal')}
                </label>
                <div className="mt-2 flex flex-col gap-3">
                    <div className="flex items-center gap-2">
                        <input
                            type="number"
                            min={50}
                            max={50000}
                            value={inputValue}
                            onChange={(e) => { setInputValue(e.target.value); setSaveError(false); }}
                            onKeyDown={handleKeyDown}
                            className="w-28 rounded-md border border-border bg-surface px-3 py-1.5 text-sm text-ink focus:border-ink focus:ring-0 focus:outline-none"
                            autoFocus
                        />
                        <span className="text-xs text-ink-faint">{t('writingGoal.wordsPerDay')}</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <input
                            type="number"
                            min={1000}
                            max={500000}
                            value={targetValue}
                            onChange={(e) => { setTargetValue(e.target.value); setSaveError(false); }}
                            onKeyDown={handleKeyDown}
                            placeholder={t('writingGoal.optional')}
                            className="w-28 rounded-md border border-border bg-surface px-3 py-1.5 text-sm text-ink placeholder:text-ink-faint/50 focus:border-ink focus:ring-0 focus:outline-none"
                        />
                        <span className="text-xs text-ink-faint">{t('writingGoal.manuscriptTarget')}</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="flex-1" />
                        <button
                            type="button"
                            onClick={() => {
                                setIsEditing(false);
                                setInputValue(String(goal ?? 500));
                                setSaveError(false);
                            }}
                            className="rounded-md px-3 py-1.5 text-xs text-ink-muted transition-colors hover:bg-neutral-bg"
                        >
                            {t('writingGoal.cancel')}
                        </button>
                        <button
                            type="button"
                            onClick={handleSave}
                            className="rounded-md bg-ink px-3 py-1.5 text-xs font-medium text-surface transition-colors hover:bg-ink/90"
                        >
                            {t('writingGoal.save')}
                        </button>
                    </div>
                    {saveError && (
                        <p className="text-xs text-red-600">{t('writingGoal.saveError')}</p>
                    )}
                </div>
            </div>
        );
    }

    // Goal display
    const progress = goal > 0 ? Math.min(100, Math.round((writingGoal.today_words / goal) * 100)) : 0;
    const wordsToGo = Math.max(0, goal - writingGoal.today_words);

    return (
        <div className="flex flex-col gap-3 rounded-lg bg-surface-card px-6 py-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-muted">
                    {t('writingGoal.todaysWriting')}
                </span>
                <button
                    type="button"
                    onClick={() => setIsEditing(true)}
                    className="text-[12px] text-ink-faint transition-colors hover:text-ink"
                >
                    {t('writingGoal.editGoal')}
                </button>
            </div>

            {/* Large number display */}
            <div>
                <span className="font-serif text-[48px] leading-[1] font-medium text-ink">
                    {writingGoal.today_words.toLocaleString(i18n.language)}
                </span>
                <span className="ml-1 font-serif text-[24px] text-ink-faint">
                    / {goal.toLocaleString(i18n.language)}
                </span>
            </div>
            <span className="mt-[-6px] text-[13px] text-ink-muted">{t('writingGoal.wordsToday')}</span>

            {/* Progress bar */}
            <div className="h-1.5 overflow-hidden rounded-[3px] bg-neutral-bg">
                <div
                    className="h-full rounded-[3px] bg-status-final transition-all duration-500"
                    style={{ width: `${progress}%` }}
                />
            </div>

            {/* Bottom row */}
            <div className="flex items-center justify-between">
                <span className="text-[13px] text-ink-muted">
                    {wordsToGo > 0
                        ? t('writingGoal.wordsToGo', { value: wordsToGo.toLocaleString(i18n.language) })
                        : t('writingGoal.goalReached')}
                </span>

                {writingGoal.streak > 0 && (
                    <span className="flex items-center gap-1 text-[13px] font-medium text-accent">
                        <FlameIcon className="text-accent" />
                        {t('writingGoal.streak', { count: writingGoal.streak })}
                    </span>
                )}
            </div>
        </div>
    );
}
