import { Check } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import Dialog from '@/components/ui/Dialog';
import { cn } from '@/lib/utils';
import type { PreparationStepKey } from '@/types/models';

/**
 * Wires the step-selection dialog to a preparation trigger. Returns a function
 * to open the dialog and the dialog element to render (it portals, so placement
 * in the tree is irrelevant).
 */
export function usePrepareStepsDialog(
    handleStart: (steps: PreparationStepKey[]) => void,
    starting: boolean,
) {
    const [open, setOpen] = useState(false);

    const stepsDialog = open ? (
        <AiPrepareStepsDialog
            starting={starting}
            onClose={() => setOpen(false)}
            onConfirm={(steps) => {
                handleStart(steps);
                setOpen(false);
            }}
        />
    ) : null;

    return { openStepsDialog: () => setOpen(true), stepsDialog };
}

type Step = {
    key: PreparationStepKey;
    requires?: PreparationStepKey;
};

const STEPS: Step[] = [
    { key: 'semantic_index' },
    { key: 'writing_style' },
    { key: 'chapter_analysis' },
    { key: 'wiki' },
    { key: 'story_bible', requires: 'chapter_analysis' },
    { key: 'health', requires: 'chapter_analysis' },
];

export default function AiPrepareStepsDialog({
    onClose,
    onConfirm,
    starting = false,
}: {
    onClose: () => void;
    onConfirm: (steps: PreparationStepKey[]) => void;
    starting?: boolean;
}) {
    const { t } = useTranslation('ai');

    const [selected, setSelected] = useState<
        Record<PreparationStepKey, boolean>
    >(
        () =>
            Object.fromEntries(STEPS.map((step) => [step.key, true])) as Record<
                PreparationStepKey,
                boolean
            >,
    );

    const isLocked = (step: Step) =>
        step.requires ? !selected[step.requires] : false;

    const isChecked = (step: Step) => selected[step.key] && !isLocked(step);

    const toggle = (step: Step) => {
        if (isLocked(step)) return;

        setSelected((prev) => {
            const next = { ...prev, [step.key]: !prev[step.key] };

            // Turning off a step also clears any step that depends on it.
            if (!next[step.key]) {
                for (const dependent of STEPS) {
                    if (dependent.requires === step.key) {
                        next[dependent.key] = false;
                    }
                }
            }

            return next;
        });
    };

    const selectedKeys = STEPS.filter((step) => isChecked(step)).map(
        (step) => step.key,
    );
    const canRun = selectedKeys.length > 0;

    return (
        <Dialog onClose={onClose} title={t('prepareDialog.title')} width={460}>
            <h2 className="font-serif text-2xl leading-8 font-semibold tracking-[-0.01em] text-ink">
                {t('prepareDialog.title')}
            </h2>
            <p className="mt-2 text-sm text-ink-muted">
                {t('prepareDialog.description')}
            </p>

            <div className="mt-6 flex flex-col gap-1">
                {STEPS.map((step) => {
                    const locked = isLocked(step);
                    const checked = isChecked(step);

                    return (
                        <button
                            key={step.key}
                            type="button"
                            role="checkbox"
                            aria-checked={checked}
                            disabled={locked}
                            onClick={() => toggle(step)}
                            className={cn(
                                'flex items-start gap-3 rounded-md px-3 py-3 text-left transition-colors',
                                locked
                                    ? 'cursor-not-allowed opacity-50'
                                    : 'hover:bg-neutral-bg/50',
                            )}
                        >
                            <span
                                className={cn(
                                    'mt-0.5 flex h-[14px] w-[14px] shrink-0 items-center justify-center rounded',
                                    checked ? 'bg-ink' : 'border border-border',
                                )}
                            >
                                {checked && (
                                    <Check
                                        className="h-3 w-3 text-surface"
                                        strokeWidth={3}
                                    />
                                )}
                            </span>
                            <span className="flex flex-col gap-0.5">
                                <span className="text-sm font-medium text-ink">
                                    {t(`prepareDialog.step.${step.key}.label`)}
                                </span>
                                <span className="text-xs text-ink-faint">
                                    {locked
                                        ? t(
                                              'prepareDialog.requiresChapterAnalysis',
                                          )
                                        : t(
                                              `prepareDialog.step.${step.key}.description`,
                                          )}
                                </span>
                            </span>
                        </button>
                    );
                })}
            </div>

            <div className="mt-8 flex justify-end gap-2">
                <Button variant="secondary" type="button" onClick={onClose}>
                    {t('prepareDialog.cancel')}
                </Button>
                <Button
                    variant="primary"
                    type="button"
                    disabled={!canRun || starting}
                    onClick={() => onConfirm(selectedKeys)}
                >
                    {starting
                        ? t('preparation.starting')
                        : t('prepareDialog.run')}
                </Button>
            </div>
        </Dialog>
    );
}
