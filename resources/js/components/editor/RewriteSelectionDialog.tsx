import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import Dialog from '@/components/ui/Dialog';
import FormField from '@/components/ui/FormField';
import Textarea from '@/components/ui/Textarea';

// Mirrors the backend Request `hint` validation rule.
const MAX_HINT_LENGTH = 2000;
// Visual confirmation only — the model still receives the full selection.
// Head + tail so the author can verify where the rewrite starts AND ends.
const PREVIEW_EDGE_WORDS = 6;

export type RewriteSelectionDraft = {
    hint: string;
};

export const defaultRewriteSelectionDraft: RewriteSelectionDraft = {
    hint: '',
};

function truncateForPreview(text: string): string {
    const words = text.trim().split(/\s+/);
    if (words.length <= PREVIEW_EDGE_WORDS * 2) return words.join(' ');
    return `${words.slice(0, PREVIEW_EDGE_WORDS).join(' ')} … ${words.slice(-PREVIEW_EDGE_WORDS).join(' ')}`;
}

export default function RewriteSelectionDialog({
    selectionPreview,
    draft,
    onDraftChange,
    onReset,
    onSubmit,
    onClose,
}: {
    selectionPreview: string;
    draft: RewriteSelectionDraft;
    onDraftChange: (draft: RewriteSelectionDraft) => void;
    onReset: () => void;
    onSubmit: (args: RewriteSelectionDraft) => void;
    onClose: () => void;
}) {
    const { t } = useTranslation('editor');

    const isPristine = draft.hint === defaultRewriteSelectionDraft.hint;

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        onSubmit({ hint: draft.hint.trim() });
        onClose();
    }

    function handleKeyDown(e: React.KeyboardEvent<HTMLTextAreaElement>) {
        if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
            e.preventDefault();
            handleSubmit(e as unknown as FormEvent);
        }
    }

    const preview = truncateForPreview(selectionPreview);
    const selectionWordCount =
        selectionPreview.trim().match(/\S+/g)?.length ?? 0;

    return (
        <Dialog
            onClose={onClose}
            backdrop="dark"
            width={560}
            className="gap-5"
            title={t('rewriteSelection.dialogTitle', {
                defaultValue: 'Rewrite selection',
            })}
        >
            <form onSubmit={handleSubmit} className="contents">
                <div className="flex flex-col gap-1">
                    <h2 className="text-base font-medium text-ink">
                        {t('rewriteSelection.dialogTitle', {
                            defaultValue: 'Rewrite selection',
                        })}
                    </h2>
                    <p className="text-xs text-ink-muted">
                        {t('rewriteSelection.dialogSubtitle', {
                            defaultValue:
                                'AI rewrites only the selected passage using the chapter, beats, characters, and world entities. Your directive here is kept until you hit Rewrite.',
                        })}
                    </p>
                </div>

                {preview && (
                    <div className="rounded-md border border-border-light bg-surface px-3 py-2 text-xs text-ink-muted">
                        <span className="font-medium text-ink-soft">
                            {t('rewriteSelection.selectionLabelWithCount', {
                                defaultValue: 'Selection ({{count}} words)',
                                count: selectionWordCount,
                            })}
                            :{' '}
                        </span>
                        <span className="italic">{preview}</span>
                    </div>
                )}

                <FormField
                    label={t('rewriteSelection.hintLabel', {
                        defaultValue: 'Optional directive',
                    })}
                >
                    <Textarea
                        variant="dialog"
                        rows={5}
                        autoFocus
                        value={draft.hint}
                        onChange={(e) =>
                            onDraftChange({ ...draft, hint: e.target.value })
                        }
                        onKeyDown={handleKeyDown}
                        placeholder={t('rewriteSelection.hintPlaceholder', {
                            defaultValue:
                                'Tighten, shift tone, swap POV… (leave blank for a craft pass). A directive takes priority over default style preservation.',
                        })}
                        maxLength={MAX_HINT_LENGTH}
                    />
                </FormField>

                <div className="flex items-center justify-between gap-3">
                    <Button
                        variant="ghost"
                        type="button"
                        onClick={onReset}
                        disabled={isPristine}
                    >
                        {t('rewriteSelection.reset', {
                            defaultValue: 'Reset',
                        })}
                    </Button>
                    <div className="flex items-center gap-3">
                        <Button variant="ghost" type="button" onClick={onClose}>
                            {t('rewriteSelection.cancel', {
                                defaultValue: 'Cancel',
                            })}
                        </Button>
                        <Button variant="primary" type="submit">
                            {t('rewriteSelection.submit', {
                                defaultValue: 'Rewrite',
                            })}
                        </Button>
                    </div>
                </div>
            </form>
        </Dialog>
    );
}
