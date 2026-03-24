import { useState, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import Toggle from '@/components/ui/Toggle';
import SettingsLayout from '@/layouts/SettingsLayout';
import { getXsrfToken } from '@/lib/csrf';
import type { ProsePassRule } from '@/types/models';
import { updateProsePassRules } from '@/actions/App/Http/Controllers/SettingsController';

type BookData = { id: number; title: string };

interface Props {
    book: BookData;
    rules: ProsePassRule[];
}

export default function ProsePassRules({ book, rules: initialRules }: Props) {
    const { t } = useTranslation('settings');
    const [rules, setRules] = useState(initialRules);

    const toggleRule = useCallback(
        (key: string) => {
            const updated = rules.map((r) =>
                r.key === key ? { ...r, enabled: !r.enabled } : r,
            );
            setRules(updated);

            fetch(updateProsePassRules.url(), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({ rules: updated }),
            }).catch(() => {
                setRules(rules);
            });
        },
        [rules],
    );

    return (
        <SettingsLayout
            activeSection="prose-pass-rules"
            book={book}
            title={t('prosePassRules.pageTitle', { bookTitle: book.title })}
        >
            <div className="flex flex-col gap-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-[-0.01em] text-ink">
                        {t('prosePassRules.title')}
                    </h1>
                    <p className="mt-1 text-[13px] text-ink-muted">
                        {t('prosePassRules.description')}
                    </p>
                </div>

                <div className="flex flex-col">
                    {rules.map((rule, i) => (
                        <div
                            key={rule.key}
                            className={`flex items-start gap-4 py-3.5 ${
                                i < rules.length - 1
                                    ? 'border-b border-border-light'
                                    : ''
                            }`}
                        >
                            <div className="pt-0.5">
                                <Toggle
                                    checked={rule.enabled}
                                    onChange={() => toggleRule(rule.key)}
                                />
                            </div>
                            <div className="flex-1">
                                <span className="text-[14px] font-medium text-ink">
                                    {rule.label}
                                </span>
                                <p className="mt-0.5 text-[13px] text-ink-muted">
                                    {rule.description}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </SettingsLayout>
    );
}
