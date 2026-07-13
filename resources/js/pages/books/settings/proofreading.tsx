import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { updateProofreadingConfig } from '@/actions/App/Http/Controllers/BookSettingsController';
import { Card } from '@/components/ui/Card';
import PageHeader from '@/components/ui/PageHeader';
import Toggle from '@/components/ui/Toggle';
import BookSettingsLayout from '@/layouts/BookSettingsLayout';
import { jsonFetchHeaders } from '@/lib/utils';
import type { ProofreadingConfig, StyleCheckKey } from '@/types/models';

const STYLE_RULES: Array<{
    key: StyleCheckKey;
    labelKey: string;
    descKey: string;
}> = [
    {
        key: 'filler',
        labelKey: 'proofreading.style.filler',
        descKey: 'proofreading.style.fillerDescription',
    },
    {
        key: 'weakVerb',
        labelKey: 'proofreading.style.weakVerb',
        descKey: 'proofreading.style.weakVerbDescription',
    },
    {
        key: 'filterWord',
        labelKey: 'proofreading.style.filterWord',
        descKey: 'proofreading.style.filterWordDescription',
    },
    {
        key: 'cliche',
        labelKey: 'proofreading.style.cliche',
        descKey: 'proofreading.style.clicheDescription',
    },
    {
        key: 'pattern',
        labelKey: 'proofreading.style.pattern',
        descKey: 'proofreading.style.patternDescription',
    },
    {
        key: 'repetition',
        labelKey: 'proofreading.style.repetition',
        descKey: 'proofreading.style.repetitionDescription',
    },
    {
        key: 'rhythm',
        labelKey: 'proofreading.style.rhythm',
        descKey: 'proofreading.style.rhythmDescription',
    },
];

type BookData = { id: number; title: string };

interface Props {
    book: BookData;
    config: ProofreadingConfig;
}

export default function ProofreadingSettings({
    book,
    config: initialConfig,
}: Props) {
    const { t } = useTranslation('settings');
    const [config, setConfig] = useState(initialConfig);

    const persistConfig = useCallback(
        (updated: ProofreadingConfig) => {
            setConfig(updated);
            fetch(updateProofreadingConfig.url(book.id), {
                method: 'PUT',
                headers: jsonFetchHeaders(),
                body: JSON.stringify({ config: updated }),
            }).catch(() => setConfig(config));
        },
        [book.id, config],
    );

    return (
        <BookSettingsLayout
            activeSection="proofreading"
            book={book}
            title={t('proofreading.pageTitle', { bookTitle: book.title })}
        >
            <div className="flex flex-col gap-4">
                <PageHeader
                    title={t('proofreading.title')}
                    subtitle={t('proofreading.description')}
                />

                <Card>
                    {/* Spelling toggle */}
                    <div className="flex items-center justify-between px-6 py-3.5">
                        <div>
                            <span className="text-sm font-medium text-ink">
                                {t('proofreading.spelling.enabled')}
                            </span>
                            <p className="mt-0.5 text-[13px] text-ink-muted">
                                {t('proofreading.spelling.enabledDescription')}
                            </p>
                        </div>
                        <Toggle
                            checked={config.spelling_enabled}
                            onChange={() =>
                                persistConfig({
                                    ...config,
                                    spelling_enabled: !config.spelling_enabled,
                                })
                            }
                        />
                    </div>

                    {/* Style analysis categories (revision mode) */}
                    {STYLE_RULES.map((rule) => (
                        <div key={rule.key}>
                            <div className="border-t border-border" />
                            <div className="flex items-center justify-between px-6 py-3.5">
                                <div>
                                    <span className="text-sm font-medium text-ink">
                                        {t(rule.labelKey)}
                                    </span>
                                    <p className="mt-0.5 text-[13px] text-ink-muted">
                                        {t(rule.descKey)}
                                    </p>
                                </div>
                                <Toggle
                                    checked={config.style_checks[rule.key]}
                                    onChange={() =>
                                        persistConfig({
                                            ...config,
                                            style_checks: {
                                                ...config.style_checks,
                                                [rule.key]:
                                                    !config.style_checks[
                                                        rule.key
                                                    ],
                                            },
                                        })
                                    }
                                />
                            </div>
                        </div>
                    ))}
                </Card>
            </div>
        </BookSettingsLayout>
    );
}
