import type { FormEvent } from 'react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import Dialog from '@/components/ui/Dialog';
import FormField from '@/components/ui/FormField';
import Textarea from '@/components/ui/Textarea';

// Mirrors the backend Request `hint` validation rule.
const MAX_HINT_LENGTH = 1000;
// Visual confirmation only — the model still receives the full selection.
const PREVIEW_WORD_LIMIT = 12;

function truncateForPreview(text: string): string {
    const words = text.trim().split(/\s+/);
    if (words.length <= PREVIEW_WORD_LIMIT) return words.join(' ');
    return `${words.slice(0, PREVIEW_WORD_LIMIT).join(' ')}…`;
}

export default function RewriteSelectionDialog({
    selectionPreview,
    onSubmit,
    onClose,
}: {
    selectionPreview: string;
    onSubmit: (args: { hint: string }) => void;
    onClose: () => void;
}) {
    const { t } = useTranslation('editor');
    const [hint, setHint] = useState('');

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        onSubmit({ hint: hint.trim() });
        onClose();
    }

    function handleKeyDown(e: React.KeyboardEvent<HTMLTextAreaElement>) {
        if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
            e.preventDefault();
            handleSubmit(e as unknown as FormEvent);
        }
    }

    const preview = truncateForPreview(selectionPreview);

    return (
        <Dialog
            onClose={onClose}
            backdrop="dark"
            width={460}
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
                                'AI rewrites the selected passage using the chapter, beats, characters, and world entities.',
                        })}
                    </p>
                </div>

                {preview && (
                    <div className="rounded-md border border-border-light bg-surface px-3 py-2 text-xs text-ink-muted">
                        <span className="font-medium text-ink-soft">
                            {t('rewriteSelection.selectionLabel', {
                                defaultValue: 'Selection',
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
                        rows={3}
                        autoFocus
                        value={hint}
                        onChange={(e) => setHint(e.target.value)}
                        onKeyDown={handleKeyDown}
                        placeholder={t('rewriteSelection.hintPlaceholder', {
                            defaultValue:
                                'Tighten, shift tone, swap POV… (leave blank for a craft pass)',
                        })}
                        maxLength={MAX_HINT_LENGTH}
                    />
                </FormField>

                <div className="flex items-center justify-end gap-3">
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
            </form>
        </Dialog>
    );
}
