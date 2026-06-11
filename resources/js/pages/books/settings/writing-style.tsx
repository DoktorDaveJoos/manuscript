import { Sparkles } from 'lucide-react';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    regenerateWritingStyle,
    updateWritingStyle,
} from '@/actions/App/Http/Controllers/BookSettingsController';
import Button from '@/components/ui/Button';
import PageHeader from '@/components/ui/PageHeader';
import { Spinner } from '@/components/ui/spinner';
import Textarea from '@/components/ui/Textarea';
import { useAiFeatures } from '@/hooks/useAiFeatures';
import BookSettingsLayout from '@/layouts/BookSettingsLayout';
import { jsonFetchHeaders } from '@/lib/utils';

type BookData = {
    id: number;
    title: string;
    writing_style_text: string | null;
};

interface Props {
    book: BookData;
    writing_style_display: string;
}

export default function WritingStyle({ book, writing_style_display }: Props) {
    const { t } = useTranslation('settings');
    const ai = useAiFeatures();
    const [text, setText] = useState(
        book.writing_style_text ?? writing_style_display,
    );
    const [saving, setSaving] = useState(false);
    const [regenerating, setRegenerating] = useState(false);
    const [message, setMessage] = useState('');

    const handleSave = useCallback(() => {
        setSaving(true);
        setMessage('');

        fetch(updateWritingStyle.url(book.id), {
            method: 'PUT',
            headers: jsonFetchHeaders(),
            body: JSON.stringify({ writing_style_text: text }),
        })
            .then(async (res) => {
                if (!res.ok) throw new Error('Save failed');
                const json = await res.json();
                setMessage(json.message);
                setTimeout(() => setMessage(''), 3000);
            })
            .catch(() => setMessage(t('writingStyle.saveFailed')))
            .finally(() => setSaving(false));
    }, [book.id, text, t]);

    const handleRegenerate = useCallback(() => {
        setRegenerating(true);
        setMessage('');

        fetch(regenerateWritingStyle.url(book.id), {
            method: 'POST',
            headers: jsonFetchHeaders(),
        })
            .then(async (res) => {
                const json = await res.json();
                if (!res.ok) {
                    throw new Error(json.message ?? 'Regenerate failed');
                }
                setText(json.writing_style_text);
                setMessage(json.message);
                setTimeout(() => setMessage(''), 3000);
            })
            .catch(() => setMessage(t('writingStyle.regenerateFailed')))
            .finally(() => setRegenerating(false));
    }, [book.id, t]);

    return (
        <BookSettingsLayout
            activeSection="writing-style"
            book={book}
            title={t('writingStyle.pageTitle', { bookTitle: book.title })}
        >
            <div className="flex flex-col gap-4">
                <PageHeader
                    title={t('writingStyle.title')}
                    subtitle={t('writingStyle.description')}
                    actions={
                        ai.usable ? (
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={handleRegenerate}
                                disabled={regenerating}
                            >
                                {regenerating ? (
                                    <Spinner className="size-[14px]" />
                                ) : (
                                    <Sparkles size={14} />
                                )}
                                {regenerating
                                    ? t('writingStyle.regenerating')
                                    : t('writingStyle.regenerate')}
                            </Button>
                        ) : undefined
                    }
                />

                <div>
                    <Textarea
                        value={text}
                        onChange={(e) => setText(e.target.value)}
                        onBlur={handleSave}
                        placeholder={t('writingStyle.placeholder')}
                        rows={10}
                        className="resize-y leading-relaxed"
                        disabled={regenerating}
                    />
                    {(message || saving) && (
                        <span className="mt-2 block text-[12px] font-medium text-status-final">
                            {saving ? t('writingStyle.saving') : message}
                        </span>
                    )}
                </div>
            </div>
        </BookSettingsLayout>
    );
}
