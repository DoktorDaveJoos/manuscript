import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { updateProofreadingConfig } from '@/actions/App/Http/Controllers/BookSettingsController';
import { Card } from '@/components/ui/Card';
import PageHeader from '@/components/ui/PageHeader';
import Toggle from '@/components/ui/Toggle';
import BookSettingsLayout from '@/layouts/BookSettingsLayout';
import { jsonFetchHeaders } from '@/lib/utils';
import type { GrammarCheckKey, ProofreadingConfig } from '@/types/models';

const GRAMMAR_RULES: Array<{
    key: GrammarCheckKey;
    labelKey: string;
    descKey: string;
}> = [
    {
        key: 'illusion',
        labelKey: 'proofreading.grammar.illusion',
        descKey: 'proofreading.grammar.illusionDescription',
    },
    {
        key: 'so',
        labelKey: 'proofreading.grammar.so',
        descKey: 'proofreading.grammar.soDescription',
    },
    {
        key: 'thereIs',
        labelKey: 'proofreading.grammar.thereIs',
        descKey: 'proofreading.grammar.thereIsDescription',
    },
    {
        key: 'tooWordy',
        labelKey: 'proofreading.grammar.tooWordy',
        descKey: 'proofreading.grammar.tooWordyDescription',
    },
    {
        key: 'passive',
        labelKey: 'proofreading.grammar.passive',
        descKey: 'proofreading.grammar.passiveDescription',
    },
    {
        key: 'weasel',
        labelKey: 'proofreading.grammar.weasel',
        descKey: 'proofreading.grammar.weaselDescription',
    },
    {
        key: 'adverb',
        labelKey: 'proofreading.grammar.adverb',
        descKey: 'proofreading.grammar.adverbDescription',
    },
    {
        key: 'cliches',
        labelKey: 'proofreading.grammar.cliches',
        descKey: 'proofreading.grammar.clichesDescription',
    },
    {
        key: 'eprime',
        labelKey: 'proofreading.grammar.eprime',
        descKey: 'proofreading.grammar.eprimeDescription',
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

                    <div className="border-t border-border" />

                    {/* Grammar toggle */}
                    <div className="flex items-center justify-between px-6 py-3.5">
                        <div>
                            <span className="text-sm font-medium text-ink">
                                {t('proofreading.grammar.enabled')}
                            </span>
                            <p className="mt-0.5 text-[13px] text-ink-muted">
                                {t('proofreading.grammar.enabledDescription')}
                            </p>
                        </div>
                        <Toggle
                            checked={config.grammar_enabled}
                            onChange={() =>
                                persistConfig({
                                    ...config,
                                    grammar_enabled: !config.grammar_enabled,
                                })
                            }
                        />
                    </div>

                    {/* Individual grammar rules */}
                    {config.grammar_enabled &&
                        GRAMMAR_RULES.map((rule) => (
                            <div key={rule.key}>
                                <div className="border-t border-border" />
                                <div className="flex items-center justify-between px-6 py-3.5 pl-10">
                                    <div>
                                        <span className="text-sm font-medium text-ink">
                                            {t(rule.labelKey)}
                                        </span>
                                        <p className="mt-0.5 text-[13px] text-ink-muted">
                                            {t(rule.descKey)}
                                        </p>
                                    </div>
                                    <Toggle
                                        checked={
                                            config.grammar_checks[rule.key]
                                        }
                                        onChange={() =>
                                            persistConfig({
                                                ...config,
                                                grammar_checks: {
                                                    ...config.grammar_checks,
                                                    [rule.key]:
                                                        !config.grammar_checks[
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
