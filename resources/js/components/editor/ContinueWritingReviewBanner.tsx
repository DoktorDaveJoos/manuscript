import { Sparkles, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/Alert';
import Button from '@/components/ui/Button';

export default function ContinueWritingReviewBanner({
    addedWords,
    onReview,
    onDismiss,
}: {
    addedWords: number;
    onReview: () => void;
    onDismiss: () => void;
}) {
    const { t } = useTranslation('editor');

    return (
        <div className="px-6 pt-3">
            <Alert
                variant="default"
                className="flex flex-row items-center gap-3 py-3"
            >
                <Sparkles size={16} className="shrink-0 text-accent" />
                <div className="flex flex-1 flex-col gap-0.5">
                    <AlertTitle>
                        {t('continueWriting.banner.title', {
                            defaultValue: 'AI continuation applied',
                        })}
                    </AlertTitle>
                    <AlertDescription>
                        {addedWords > 0
                            ? t('continueWriting.banner.descriptionWithCount', {
                                  defaultValue:
                                      'Added about {{count}} words. Review the changes to keep or revert paragraphs.',
                                  count: addedWords,
                              })
                            : t('continueWriting.banner.description', {
                                  defaultValue:
                                      'Review the changes to keep or revert paragraphs.',
                              })}
                    </AlertDescription>
                </div>
                <Button
                    variant="primary"
                    size="sm"
                    type="button"
                    onClick={onReview}
                >
                    {t('continueWriting.banner.review', {
                        defaultValue: 'Review changes',
                    })}
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    type="button"
                    onClick={onDismiss}
                    aria-label={t('continueWriting.banner.dismiss', {
                        defaultValue: 'Dismiss',
                    })}
                >
                    <X size={14} />
                </Button>
            </Alert>
        </div>
    );
}
