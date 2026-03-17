import { useState, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { updateWritingStyle } from '@/actions/App/Http/Controllers/SettingsController';
import Textarea from '@/components/ui/Textarea';
import SettingsLayout from '@/layouts/SettingsLayout';
import { getXsrfToken } from '@/lib/csrf';

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
    const [text, setText] = useState(
        book.writing_style_text ?? writing_style_display,
    );
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState('');

    const handleSave = useCallback(() => {
        setSaving(true);
        setMessage('');

        fetch(updateWritingStyle.url(), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
                Accept: 'application/json',
            },
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
    }, [text, t]);

    return (
        <SettingsLayout
            activeSection="writing-style"
            book={book}
            title={t('writingStyle.pageTitle', { bookTitle: book.title })}
        >
            <div className="flex flex-col gap-4">
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-[22px] font-semibold tracking-[-0.01em] text-ink">
                            {t('writingStyle.title')}
                        </h1>
                        <p className="mt-1 text-[13px] text-ink-muted">
                            {t('writingStyle.description')}
                        </p>
                    </div>
                </div>

                <div>
                    <Textarea
                        value={text}
                        onChange={(e) => setText(e.target.value)}
                        onBlur={handleSave}
                        placeholder={t('writingStyle.placeholder')}
                        rows={10}
                        className="resize-y leading-relaxed"
                    />
                    {(message || saving) && (
                        <span className="mt-2 block text-[12px] font-medium text-status-final">
                            {saving ? t('writingStyle.saving') : message}
                        </span>
                    )}
                </div>
            </div>
        </SettingsLayout>
    );
}
