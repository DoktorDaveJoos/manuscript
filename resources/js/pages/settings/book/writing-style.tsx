import {
    updateWritingStyle,
    regenerateWritingStyle,
} from '@/actions/App/Http/Controllers/BookSettingsController';
import SettingsLayout from '@/layouts/SettingsLayout';
import { getXsrfToken } from '@/lib/csrf';
import { useState, useCallback } from 'react';

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
    const [text, setText] = useState(book.writing_style_text ?? writing_style_display);
    const [saving, setSaving] = useState(false);
    const [regenerating, setRegenerating] = useState(false);
    const [message, setMessage] = useState('');

    const handleSave = useCallback(() => {
        setSaving(true);
        setMessage('');

        fetch(updateWritingStyle.url(book), {
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
            .catch(() => setMessage('Failed to save.'))
            .finally(() => setSaving(false));
    }, [book, text]);

    const handleRegenerate = useCallback(() => {
        setRegenerating(true);
        setMessage('');

        fetch(regenerateWritingStyle.url(book), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
                Accept: 'application/json',
            },
        })
            .then(async (res) => {
                const json = await res.json();
                if (!res.ok) {
                    setMessage(json.message || 'Failed to regenerate.');
                    return;
                }
                setText(json.writing_style_text);
                setMessage(json.message);
                setTimeout(() => setMessage(''), 3000);
            })
            .catch(() => setMessage('Failed to regenerate writing style.'))
            .finally(() => setRegenerating(false));
    }, [book]);

    return (
        <SettingsLayout activeSection="writing-style" book={book} title={`Writing Style — ${book.title}`}>
            <div className="flex flex-col gap-4">
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-[22px] font-semibold tracking-[-0.01em] text-ink">Writing Style</h1>
                        <p className="mt-1 text-[13px] text-ink-muted">
                            Describe the prose voice for AI to follow when working with this book.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={handleRegenerate}
                        disabled={regenerating}
                        className="h-8 rounded-md border border-border px-3.5 text-[13px] font-medium text-ink transition-colors hover:bg-neutral-bg disabled:opacity-50"
                    >
                        {regenerating ? 'Regenerating...' : 'Regenerate'}
                    </button>
                </div>

                <div>
                    <textarea
                        value={text}
                        onChange={(e) => setText(e.target.value)}
                        onBlur={handleSave}
                        placeholder="Describe the prose voice, sentence rhythm, vocabulary, and distinctive features..."
                        rows={10}
                        className="w-full resize-y rounded-md border border-border bg-surface px-3 py-2.5 text-[14px] leading-relaxed text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none"
                    />
                    {(message || saving) && (
                        <span className="mt-2 block text-[12px] font-medium text-status-final">
                            {saving ? 'Saving...' : message}
                        </span>
                    )}
                </div>
            </div>
        </SettingsLayout>
    );
}
