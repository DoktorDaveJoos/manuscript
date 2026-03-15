import { useTranslation } from 'react-i18next';
import { AiSetting, License } from '@/types/models';
import { update, test as testConnection } from '@/actions/App/Http/Controllers/AiSettingsController';
import SettingsLayout from '@/layouts/SettingsLayout';
import { getXsrfToken } from '@/lib/csrf';
import { router, usePage } from '@inertiajs/react';
import { useState, useCallback, type FormEvent } from 'react';

type ProviderSetting = AiSetting & { label: string; supports_embeddings: boolean };

interface Props {
    settings: ProviderSetting[];
    book?: { id: number; title: string } | null;
}

type TestStatus = { type: 'idle' } | { type: 'loading' } | { type: 'success'; message: string } | { type: 'error'; message: string };

function ProviderCard({
    setting,
    locked,
    isSelected,
    onSelect,
}: {
    setting: ProviderSetting;
    locked: boolean;
    isSelected: boolean;
    onSelect: () => void;
}) {
    const { t } = useTranslation('settings');
    const [apiKey, setApiKey] = useState('');
    const [baseUrl, setBaseUrl] = useState(setting.base_url ?? '');
    const [apiVersion, setApiVersion] = useState(setting.api_version ?? '');
    const [textModel, setTextModel] = useState(setting.text_model ?? '');
    const [embeddingModel, setEmbeddingModel] = useState(setting.embedding_model ?? '');
    const [embeddingDimensions, setEmbeddingDimensions] = useState(setting.embedding_dimensions?.toString() ?? '');
    const [saving, setSaving] = useState(false);
    const [testStatus, setTestStatus] = useState<TestStatus>({ type: 'idle' });
    const [saveMessage, setSaveMessage] = useState('');

    const isAzure = setting.provider === 'azure';
    const isOllama = setting.provider === 'ollama';

    const handleSave = useCallback(
        (e: FormEvent) => {
            e.preventDefault();
            setSaving(true);
            setSaveMessage('');

            const data: Record<string, unknown> = { enabled: true };
            if (apiKey) data.api_key = apiKey;
            if (baseUrl) data.base_url = baseUrl;
            if (textModel) data.text_model = textModel;
            if (apiVersion) data.api_version = apiVersion;
            if (embeddingModel) data.embedding_model = embeddingModel;
            if (embeddingDimensions) data.embedding_dimensions = parseInt(embeddingDimensions, 10);

            fetch(update.url(setting.provider), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify(data),
            })
                .then(async (res) => {
                    if (!res.ok) throw new Error('Save failed');
                    const json = await res.json();
                    setSaveMessage(json.message);
                    setApiKey('');
                    router.reload({ only: ['settings'] });
                    setTimeout(() => setSaveMessage(''), 3000);
                })
                .catch(() => setSaveMessage(t('aiProviders.saveFailed')))
                .finally(() => setSaving(false));
        },
        [apiKey, baseUrl, apiVersion, textModel, embeddingModel, embeddingDimensions, setting.provider],
    );

    const handleTest = useCallback(() => {
        setTestStatus({ type: 'loading' });

        fetch(testConnection.url(setting.provider), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
                Accept: 'application/json',
            },
        })
            .then(async (res) => {
                const json = await res.json();
                setTestStatus(json.success ? { type: 'success', message: json.message } : { type: 'error', message: json.message });
                setTimeout(() => setTestStatus({ type: 'idle' }), 5000);
            })
            .catch(() => {
                setTestStatus({ type: 'error', message: t('aiProviders.testFailed') });
                setTimeout(() => setTestStatus({ type: 'idle' }), 5000);
            });
    }, [setting.provider]);

    const configured = setting.requires_api_key ? setting.has_api_key : !!setting.base_url;

    return (
        <div className={`rounded-lg border border-border bg-surface-card ${locked ? 'opacity-50' : ''}`}>
            {/* Header row — radio select */}
            <button
                type="button"
                onClick={onSelect}
                disabled={locked}
                className="flex w-full items-center justify-between px-5 py-4 text-left"
            >
                <div className="flex items-center gap-3">
                    {locked ? (
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" className="text-ink-faint">
                            <rect x="3" y="7" width="10" height="7" rx="1.5" stroke="currentColor" strokeWidth="1.5" />
                            <path d="M5 7V5a3 3 0 016 0v2" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                        </svg>
                    ) : (
                        <span
                            className={`flex size-[18px] items-center justify-center rounded-full border-2 transition-colors ${
                                isSelected ? 'border-accent' : 'border-border'
                            }`}
                        >
                            {isSelected && <span className="size-[10px] rounded-full bg-accent" />}
                        </span>
                    )}
                    <span className="text-[15px] font-medium text-ink">{setting.label}</span>
                    {!locked && (
                        <span
                            className={`rounded-full px-2 py-0.5 text-[11px] font-medium ${
                                configured ? 'bg-status-final/15 text-status-final' : 'bg-neutral-bg text-ink-muted'
                            }`}
                        >
                            {configured ? t('aiProviders.configured') : t('aiProviders.notConfigured')}
                        </span>
                    )}
                </div>
            </button>

            {/* Expanded form when selected and not locked */}
            {isSelected && !locked && (
                <form onSubmit={handleSave} className="border-t border-border-light px-5 pb-5 pt-4">
                    <div className="flex flex-col gap-4">
                        {setting.requires_api_key && (
                            <label className="flex flex-col gap-1.5">
                                <span className="text-[13px] font-medium text-ink-muted">{t('aiProviders.apiKey')}</span>
                                <input
                                    type="password"
                                    value={apiKey}
                                    onChange={(e) => setApiKey(e.target.value)}
                                    placeholder={setting.has_api_key ? t('aiProviders.apiKeyMask') : t('aiProviders.apiKeyPlaceholder')}
                                    className="h-9 rounded-md border border-border bg-surface px-3 text-[13px] text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none"
                                />
                            </label>
                        )}

                        {setting.requires_base_url && (
                            <label className="flex flex-col gap-1.5">
                                <span className="text-[13px] font-medium text-ink-muted">{t('aiProviders.baseUrl')}</span>
                                <input
                                    type="url"
                                    value={baseUrl}
                                    onChange={(e) => setBaseUrl(e.target.value)}
                                    placeholder={isOllama ? 'http://localhost:11434' : 'https://your-resource.openai.azure.com'}
                                    className="h-9 rounded-md border border-border bg-surface px-3 text-[13px] text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none"
                                />
                            </label>
                        )}

                        {isAzure && (
                            <label className="flex flex-col gap-1.5">
                                <span className="text-[13px] font-medium text-ink-muted">{t('aiProviders.apiVersion')}</span>
                                <input
                                    type="text"
                                    value={apiVersion}
                                    onChange={(e) => setApiVersion(e.target.value)}
                                    placeholder="2024-10-21"
                                    className="h-9 rounded-md border border-border bg-surface px-3 text-[13px] text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none"
                                />
                            </label>
                        )}

                        <label className="flex flex-col gap-1.5">
                            <span className="text-[13px] font-medium text-ink-muted">
                                {isAzure ? t('aiProviders.deploymentName') : t('aiProviders.textModel')}
                            </span>
                            <input
                                type="text"
                                value={textModel}
                                onChange={(e) => setTextModel(e.target.value)}
                                placeholder={isAzure ? 'gpt-4o' : t('aiProviders.modelPlaceholder')}
                                className="h-9 rounded-md border border-border bg-surface px-3 text-[13px] text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none"
                            />
                        </label>

                        {setting.supports_embeddings && (
                            <div className="flex gap-3">
                                <label className="flex flex-1 flex-col gap-1.5">
                                    <span className="text-[13px] font-medium text-ink-muted">
                                        {isAzure ? t('aiProviders.embeddingDeployment') : t('aiProviders.embeddingModel')}
                                    </span>
                                    <input
                                        type="text"
                                        value={embeddingModel}
                                        onChange={(e) => setEmbeddingModel(e.target.value)}
                                        placeholder={isAzure ? 'text-embedding-3-small' : t('aiProviders.modelPlaceholder')}
                                        className="h-9 rounded-md border border-border bg-surface px-3 text-[13px] text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none"
                                    />
                                </label>
                                <label className="flex w-32 flex-col gap-1.5">
                                    <span className="text-[13px] font-medium text-ink-muted">{t('aiProviders.dimensions')}</span>
                                    <input
                                        type="number"
                                        value={embeddingDimensions}
                                        onChange={(e) => setEmbeddingDimensions(e.target.value)}
                                        placeholder="1536"
                                        className="h-9 rounded-md border border-border bg-surface px-3 text-[13px] text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none"
                                    />
                                </label>
                            </div>
                        )}

                        <div className="flex items-center gap-3 pt-1">
                            <button
                                type="submit"
                                disabled={saving}
                                className="h-8 rounded-md bg-ink px-3.5 text-[13px] font-medium text-surface transition-opacity hover:opacity-90 disabled:opacity-50"
                            >
                                {saving ? t('aiProviders.saving') : t('aiProviders.save')}
                            </button>
                            <button
                                type="button"
                                onClick={handleTest}
                                disabled={testStatus.type === 'loading' || !configured}
                                className="h-8 rounded-md border border-border px-3.5 text-[13px] font-medium text-ink transition-colors hover:bg-neutral-bg disabled:opacity-50"
                            >
                                {testStatus.type === 'loading' ? t('aiProviders.testing') : t('aiProviders.testConnection')}
                            </button>

                            {saveMessage && (
                                <span className="text-[12px] font-medium text-status-final">{saveMessage}</span>
                            )}
                            {testStatus.type === 'success' && (
                                <span className="text-[12px] font-medium text-status-final">{testStatus.message}</span>
                            )}
                            {testStatus.type === 'error' && (
                                <span className="text-[12px] font-medium text-danger">{testStatus.message}</span>
                            )}
                        </div>
                    </div>
                </form>
            )}
        </div>
    );
}

export default function AiProviders({ settings, book }: Props) {
    const { t } = useTranslation('settings');
    const { license } = usePage<{ license: License }>().props;
    const locked = !license.active;

    const handleSelect = useCallback(
        (provider: string) => {
            if (locked) return;

            fetch(update.url(provider), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({ enabled: true }),
            }).then(() => {
                router.reload({ only: ['settings'] });
            });
        },
        [locked],
    );

    return (
        <SettingsLayout activeSection="ai-providers" book={book} title={t('aiProviders.title')}>
            <div className="flex flex-col gap-6">
                <div>
                    <h1 className="text-[22px] font-semibold tracking-[-0.01em] text-ink">{t('aiProviders.title')}</h1>
                    <p className="mt-1 text-[14px] text-ink-muted">
                        {t('aiProviders.description')}
                    </p>
                </div>

                <div className="flex flex-col gap-3">
                    {settings.map((setting) => (
                        <ProviderCard
                            key={setting.provider}
                            setting={setting}
                            locked={locked}
                            isSelected={setting.enabled}
                            onSelect={() => handleSelect(setting.provider)}
                        />
                    ))}
                </div>
            </div>
        </SettingsLayout>
    );
}
