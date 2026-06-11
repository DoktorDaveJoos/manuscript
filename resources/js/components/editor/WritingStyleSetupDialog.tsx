import { Sparkles } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    dismissWritingStylePrompt,
    regenerateWritingStyle,
} from '@/actions/App/Http/Controllers/BookSettingsController';
import Button from '@/components/ui/Button';
import Checkbox from '@/components/ui/Checkbox';
import Dialog from '@/components/ui/Dialog';
import { Spinner } from '@/components/ui/spinner';
import { jsonFetchHeaders } from '@/lib/utils';

export type WritingStyleGateOutcome = 'generated' | 'dismissed' | 'skipped';

/**
 * Pre-flight offer shown when a prose-generating AI feature runs on a book
 * without a writing style. Generating happens inline; the pending feature
 * proceeds afterwards either way via onProceed.
 */
export default function WritingStyleSetupDialog({
    bookId,
    onProceed,
    onClose,
}: {
    bookId: number;
    onProceed: (outcome: WritingStyleGateOutcome) => void;
    onClose: () => void;
}) {
    const { t } = useTranslation('editor');
    const [analyzing, setAnalyzing] = useState(false);
    const [dontAskAgain, setDontAskAgain] = useState(false);
    const [error, setError] = useState('');

    const handleAnalyze = () => {
        setAnalyzing(true);
        setError('');

        fetch(regenerateWritingStyle.url(bookId), {
            method: 'POST',
            headers: jsonFetchHeaders(),
        })
            .then(async (res) => {
                const json = await res.json().catch(() => null);
                if (!res.ok) {
                    throw new Error(json?.message ?? '');
                }
                onProceed('generated');
            })
            .catch((e: Error) => {
                setError(e.message || t('writingStyleGate.error'));
                setAnalyzing(false);
            });
    };

    const handleSkip = () => {
        if (dontAskAgain) {
            // Fire and forget — the feature must not wait on bookkeeping.
            void fetch(dismissWritingStylePrompt.url(bookId), {
                method: 'POST',
                headers: jsonFetchHeaders(),
            }).catch(() => {});
        }
        onProceed(dontAskAgain ? 'dismissed' : 'skipped');
    };

    return (
        <Dialog
            onClose={analyzing ? () => {} : onClose}
            backdrop="dark"
            width={480}
            className="gap-5"
            title={t('writingStyleGate.title')}
        >
            <div className="flex flex-col gap-1">
                <h2 className="text-base font-medium text-ink">
                    {t('writingStyleGate.title')}
                </h2>
                <p className="text-xs leading-relaxed text-ink-muted">
                    {t('writingStyleGate.body')}
                </p>
            </div>

            {error && (
                <p className="text-xs leading-relaxed text-delete">{error}</p>
            )}

            <label className="flex cursor-pointer items-center gap-2 text-xs text-ink-muted select-none">
                <Checkbox
                    checked={dontAskAgain}
                    onChange={() => setDontAskAgain(!dontAskAgain)}
                />
                {t('writingStyleGate.dontAskAgain')}
            </label>

            <div className="flex items-center justify-end gap-3">
                <Button
                    variant="ghost"
                    type="button"
                    onClick={handleSkip}
                    disabled={analyzing}
                >
                    {t('writingStyleGate.skip')}
                </Button>
                <Button
                    variant="primary"
                    type="button"
                    onClick={handleAnalyze}
                    disabled={analyzing}
                >
                    {analyzing ? (
                        <Spinner className="size-[14px]" />
                    ) : (
                        <Sparkles size={14} />
                    )}
                    {analyzing
                        ? t('writingStyleGate.analyzing')
                        : t('writingStyleGate.analyze')}
                </Button>
            </div>
        </Dialog>
    );
}
