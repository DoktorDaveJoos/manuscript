import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { update as updateWritingGoal } from '@/actions/App/Http/Controllers/WritingGoalController';
import Button from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import Input from '@/components/ui/Input';
import SectionLabel from '@/components/ui/SectionLabel';
import { jsonFetchHeaders } from '@/lib/utils';
import type { WritingGoalData } from '@/types/models';

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
    const [targetValue, setTargetValue] = useState(
        String(targetWordCount ?? ''),
    );
    const [saveError, setSaveError] = useState(false);

    const handleSave = useCallback(async () => {
        const parsed = parseInt(inputValue, 10);
        if (isNaN(parsed) || parsed < 50 || parsed > 50000) return;

        const body: Record<string, unknown> = { daily_word_count_goal: parsed };
        const parsedTarget = targetValue ? parseInt(targetValue, 10) : null;
        if (
            parsedTarget !== null &&
            (isNaN(parsedTarget) ||
                parsedTarget < 1000 ||
                parsedTarget > 500000)
        ) {
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
            <Card className="p-6">
                <p className="text-sm text-ink-muted">
                    {t('writingGoal.setupPrompt')}
                </p>
                <Button
                    variant="primary"
                    size="sm"
                    type="button"
                    onClick={() => setIsEditing(true)}
                    className="mt-3"
                >
                    {t('writingGoal.setGoal')}
                </Button>
            </Card>
        );
    }

    // Editing mode
    if (isEditing) {
        return (
            <Card className="p-6">
                <SectionLabel as="label" variant="section">
                    {t('writingGoal.dailyWordGoal')}
                </SectionLabel>
                <div className="mt-2 flex flex-col gap-4">
                    <div className="flex items-center gap-2">
                        <Input
                            type="number"
                            min={50}
                            max={50000}
                            value={inputValue}
                            onChange={(e) => {
                                setInputValue(e.target.value);
                                setSaveError(false);
                            }}
                            onKeyDown={handleKeyDown}
                            className="w-28"
                            autoFocus
                        />
                        <span className="text-xs text-ink-faint">
                            {t('writingGoal.wordsPerDay')}
                        </span>
                    </div>
                    <div className="flex items-center gap-2">
                        <Input
                            type="number"
                            min={1000}
                            max={500000}
                            value={targetValue}
                            onChange={(e) => {
                                setTargetValue(e.target.value);
                                setSaveError(false);
                            }}
                            onKeyDown={handleKeyDown}
                            placeholder={t('writingGoal.optional')}
                            className="w-28"
                        />
                        <span className="text-xs text-ink-faint">
                            {t('writingGoal.manuscriptTarget')}
                        </span>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="flex-1" />
                        <Button
                            variant="ghost"
                            size="sm"
                            type="button"
                            onClick={() => {
                                setIsEditing(false);
                                setInputValue(String(goal ?? 500));
                                setSaveError(false);
                            }}
                        >
                            {t('writingGoal.cancel')}
                        </Button>
                        <Button
                            variant="primary"
                            size="sm"
                            type="button"
                            onClick={handleSave}
                        >
                            {t('writingGoal.save')}
                        </Button>
                    </div>
                    {saveError && (
                        <p className="text-xs text-red-600">
                            {t('writingGoal.saveError')}
                        </p>
                    )}
                </div>
            </Card>
        );
    }

    // Goal display
    const effectiveGoal = goal ?? 0;
    const progress =
        effectiveGoal > 0
            ? Math.min(
                  100,
                  Math.round((writingGoal.today_words / effectiveGoal) * 100),
              )
            : 0;
    const wordsToGo = Math.max(0, effectiveGoal - writingGoal.today_words);

    return (
        <Card className="flex h-full flex-col gap-4 p-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <SectionLabel variant="section">
                    {t('writingGoal.todaysWriting')}
                </SectionLabel>
                <button
                    type="button"
                    onClick={() => setIsEditing(true)}
                    className="text-[12px] font-medium text-accent transition-colors hover:text-accent/80"
                >
                    {t('writingGoal.editGoal')}
                </button>
            </div>

            {/* Large number display */}
            <div className="flex items-end gap-1.5">
                <span className="font-serif text-[32px] leading-[0.95] font-semibold text-ink">
                    {writingGoal.today_words.toLocaleString(i18n.language)}
                </span>
                <span className="font-serif text-[24px] leading-[0.95] font-normal text-ink-faint">
                    / {effectiveGoal.toLocaleString(i18n.language)}
                </span>
            </div>

            {/* Progress bar */}
            <div className="h-1.5 overflow-hidden rounded bg-neutral-bg">
                <div
                    className="h-full rounded bg-gradient-to-r from-accent to-accent-dark transition-all duration-500"
                    style={{ width: `${progress}%` }}
                />
            </div>

            {/* Bottom row */}
            <div className="flex items-center justify-between">
                <span className="text-[12px] text-ink-muted">
                    {wordsToGo > 0
                        ? t('writingGoal.wordsToGo', {
                              value: wordsToGo.toLocaleString(i18n.language),
                          })
                        : t('writingGoal.goalReached')}
                </span>

                {writingGoal.streak > 0 && (
                    <span className="flex items-center gap-1 text-[12px] font-semibold text-accent">
                        🔥
                        {t('writingGoal.streak', { count: writingGoal.streak })}
                    </span>
                )}
            </div>
        </Card>
    );
}
