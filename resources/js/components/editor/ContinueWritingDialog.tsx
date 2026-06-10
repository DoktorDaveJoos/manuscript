import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import Dialog from '@/components/ui/Dialog';
import FormField from '@/components/ui/FormField';
import Input from '@/components/ui/Input';
import Textarea from '@/components/ui/Textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/ToggleGroup';

const DEFAULT_WORD_GOAL = 120;
const MIN_WORD_GOAL = 30;
const MAX_WORD_GOAL = 500;
const MAX_HINT_LENGTH = 2000;

export type ChapterLink = 'auto' | 'continue' | 'fresh';

export type ContinueWritingDraft = {
    hint: string;
    wordGoal: number;
    chapterLink: ChapterLink;
};

export const defaultContinueWritingDraft: ContinueWritingDraft = {
    hint: '',
    wordGoal: DEFAULT_WORD_GOAL,
    chapterLink: 'auto',
};

export default function ContinueWritingDialog({
    draft,
    onDraftChange,
    onReset,
    onSubmit,
    onClose,
}: {
    draft: ContinueWritingDraft;
    onDraftChange: (draft: ContinueWritingDraft) => void;
    onReset: () => void;
    onSubmit: (args: ContinueWritingDraft) => void;
    onClose: () => void;
}) {
    const { t } = useTranslation('editor');

    const isPristine =
        draft.hint === defaultContinueWritingDraft.hint &&
        draft.wordGoal === defaultContinueWritingDraft.wordGoal &&
        draft.chapterLink === defaultContinueWritingDraft.chapterLink;

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        const clamped = Math.max(
            MIN_WORD_GOAL,
            Math.min(MAX_WORD_GOAL, draft.wordGoal || DEFAULT_WORD_GOAL),
        );
        onSubmit({
            hint: draft.hint.trim(),
            wordGoal: clamped,
            chapterLink: draft.chapterLink,
        });
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
            width={560}
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
                                'AI continues from your cursor using the chapter, beats, and entities. Your draft here is kept until you hit Continue.',
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
                        rows={6}
                        autoFocus
                        value={draft.hint}
                        onChange={(e) =>
                            onDraftChange({ ...draft, hint: e.target.value })
                        }
                        onKeyDown={handleKeyDown}
                        placeholder={t('continueWriting.hintPlaceholder', {
                            defaultValue:
                                'What should the next passage cover? A hint takes priority over the chapter beats.',
                        })}
                        maxLength={MAX_HINT_LENGTH}
                    />
                </FormField>

                <div className="flex items-start gap-5">
                    <FormField
                        label={t('continueWriting.wordGoalLabel', {
                            defaultValue: 'Word goal',
                        })}
                        className="w-28 shrink-0"
                    >
                        <Input
                            variant="dialog"
                            type="number"
                            min={MIN_WORD_GOAL}
                            max={MAX_WORD_GOAL}
                            step={10}
                            value={draft.wordGoal}
                            onChange={(e) =>
                                onDraftChange({
                                    ...draft,
                                    wordGoal: parseInt(e.target.value, 10) || 0,
                                })
                            }
                        />
                    </FormField>

                    <FormField
                        label={t('continueWriting.chapterLinkLabel', {
                            defaultValue: 'Link to previous chapter',
                        })}
                        className="flex-1"
                    >
                        <ToggleGroup
                            type="single"
                            value={draft.chapterLink}
                            onValueChange={(next) => {
                                if (
                                    next === 'auto' ||
                                    next === 'continue' ||
                                    next === 'fresh'
                                ) {
                                    onDraftChange({
                                        ...draft,
                                        chapterLink: next,
                                    });
                                }
                            }}
                        >
                            <ToggleGroupItem value="auto">
                                {t('continueWriting.chapterLinkAuto', {
                                    defaultValue: 'Auto',
                                })}
                            </ToggleGroupItem>
                            <ToggleGroupItem value="continue">
                                {t('continueWriting.chapterLinkContinue', {
                                    defaultValue: 'Continue on',
                                })}
                            </ToggleGroupItem>
                            <ToggleGroupItem value="fresh">
                                {t('continueWriting.chapterLinkFresh', {
                                    defaultValue: 'Fresh scene',
                                })}
                            </ToggleGroupItem>
                        </ToggleGroup>
                        <p className="text-xs text-ink-faint">
                            {t('continueWriting.chapterLinkHelp', {
                                defaultValue:
                                    'Applies when starting an empty chapter.',
                            })}
                        </p>
                    </FormField>
                </div>

                <div className="flex items-center justify-between gap-3">
                    <Button
                        variant="ghost"
                        type="button"
                        onClick={onReset}
                        disabled={isPristine}
                    >
                        {t('continueWriting.reset', {
                            defaultValue: 'Reset',
                        })}
                    </Button>
                    <div className="flex items-center gap-3">
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
                </div>
            </form>
        </Dialog>
    );
}
