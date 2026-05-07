import type { FormEvent } from 'react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import Dialog from '@/components/ui/Dialog';
import FormField from '@/components/ui/FormField';
import Input from '@/components/ui/Input';
import Textarea from '@/components/ui/Textarea';

const DEFAULT_WORD_GOAL = 120;
const MIN_WORD_GOAL = 30;
const MAX_WORD_GOAL = 500;

export default function ContinueWritingDialog({
    onSubmit,
    onClose,
}: {
    onSubmit: (args: { hint: string; wordGoal: number }) => void;
    onClose: () => void;
}) {
    const { t } = useTranslation('editor');
    const [hint, setHint] = useState('');
    const [wordGoal, setWordGoal] = useState<number>(DEFAULT_WORD_GOAL);

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        const clamped = Math.max(
            MIN_WORD_GOAL,
            Math.min(MAX_WORD_GOAL, wordGoal || DEFAULT_WORD_GOAL),
        );
        onSubmit({ hint: hint.trim(), wordGoal: clamped });
        onClose();
    }

    function handleKeyDown(e: React.KeyboardEvent<HTMLTextAreaElement>) {
        if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
            e.preventDefault();
            handleSubmit(e as unknown as FormEvent);
        }
    }

    return (
        <Dialog
            onClose={onClose}
            backdrop="dark"
            width={460}
            className="gap-5"
            title={t('continueWriting.dialogTitle', {
                defaultValue: 'Continue writing',
            })}
        >
            <form onSubmit={handleSubmit} className="contents">
                <div className="flex flex-col gap-1">
                    <h2 className="text-base font-medium text-ink">
                        {t('continueWriting.dialogTitle', {
                            defaultValue: 'Continue writing',
                        })}
                    </h2>
                    <p className="text-xs text-ink-muted">
                        {t('continueWriting.dialogSubtitle', {
                            defaultValue:
                                'AI continues from your cursor using the chapter, beats, and entities.',
                        })}
                    </p>
                </div>

                <FormField
                    label={t('continueWriting.hintLabel', {
                        defaultValue: 'Optional hint',
                    })}
                >
                    <Textarea
                        variant="dialog"
                        rows={3}
                        autoFocus
                        value={hint}
                        onChange={(e) => setHint(e.target.value)}
                        onKeyDown={handleKeyDown}
                        placeholder={t('continueWriting.hintPlaceholder', {
                            defaultValue:
                                'Tone, focus, beat to advance… (leave blank for one-shot)',
                        })}
                        maxLength={1000}
                    />
                </FormField>

                <FormField
                    label={t('continueWriting.wordGoalLabel', {
                        defaultValue: 'Word goal',
                    })}
                >
                    <Input
                        variant="dialog"
                        type="number"
                        min={MIN_WORD_GOAL}
                        max={MAX_WORD_GOAL}
                        step={10}
                        value={wordGoal}
                        onChange={(e) =>
                            setWordGoal(parseInt(e.target.value, 10) || 0)
                        }
                    />
                </FormField>

                <div className="flex items-center justify-end gap-3">
                    <Button variant="ghost" type="button" onClick={onClose}>
                        {t('continueWriting.cancel', {
                            defaultValue: 'Cancel',
                        })}
                    </Button>
                    <Button variant="primary" type="submit">
                        {t('continueWriting.submit', {
                            defaultValue: 'Continue writing',
                        })}
                    </Button>
                </div>
            </form>
        </Dialog>
    );
}
