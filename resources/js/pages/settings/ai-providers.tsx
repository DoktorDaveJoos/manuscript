import { useTranslation } from 'react-i18next';
import { AiSetting, License } from '@/types/models';
import { update, deleteKey, test as testConnection } from '@/actions/App/Http/Controllers/AiSettingsController';
import SettingsLayout from '@/layouts/SettingsLayout';
import { getXsrfToken } from '@/lib/csrf';
import { router, usePage } from '@inertiajs/react';
import { useState, useCallback, type FormEvent } from 'react';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';

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
    isExpanded,
    onToggle,
}: {
    setting: ProviderSetting;
    locked: boolean;
    isSelected: boolean;
    isExpanded: boolean;
    onToggle: () => void;
}) {
    const { t } = useTranslation('settings');
    const [apiKey, setApiKey] = useState('');
    const [apiKeyFocused, setApiKeyFocused] = useState(false);
    const [baseUrl, setBaseUrl] = useState(setting.base_url ?? '');
    const [apiVersion, setApiVersion] = useState(setting.api_version ?? '');
    const [writingModel, setWritingModel] = useState(setting.writing_model ?? '');
    const [analysisModel, setAnalysisModel] = useState(setting.analysis_model ?? '');
    const [extractionModel, setExtractionModel] = useState(setting.extraction_model ?? '');
    const [embeddingModel, setEmbeddingModel] = useState(setting.embedding_model ?? '');
    const [embeddingDimensions, setEmbeddingDimensions] = useState(setting.embedding_dimensions?.toString() ?? '');
    const [saving, setSaving] = useState(false);
    const [testStatus, setTestStatus] = useState<TestStatus>({ type: 'idle' });
    const [saveMessage, setSaveMessage] = useState('');
    const [advancedOpen, setAdvancedOpen] = useState(false);

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
            if (apiVersion) data.api_version = apiVersion;
            if (writingModel) data.writing_model = writingModel;
            if (analysisModel) data.analysis_model = analysisModel;
            if (extractionModel) data.extraction_model = extractionModel;
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
        [apiKey, baseUrl, apiVersion, writingModel, analysisModel, extractionModel, embeddingModel, embeddingDimensions, setting.provider],
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
        <div className={locked ? 'opacity-50' : ''}>
            {/* Header row */}
            <button
                type="button"
                onClick={onToggle}
                disabled={locked}
                className="flex w-full items-center gap-3 px-5 py-4 text-left"
            >
                {locked ? (
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" className="text-ink-faint">
                        <rect x="3" y="7" width="10" height="7" rx="1.5" stroke="currentColor" strokeWidth="1.5" />
                        <path d="M5 7V5a3 3 0 016 0v2" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                    </svg>
                ) : (
                    <span
                        className={`flex size-[18px] items-center justify-center rounded-full ${
                            isSelected ? 'bg-accent' : 'border-2 border-border'
                        }`}
                    >
                        {isSelected && <span className="size-2 rounded-full bg-white" />}
                    </span>
                )}
                <span className={`text-[15px] leading-[1.33] ${isSelected ? 'font-medium' : ''} text-ink`}>{setting.label}</span>
                {!locked && (
                    <span
                        className={`rounded px-2.5 py-1 text-[11px] font-medium ${
                            configured ? 'bg-status-final/15 text-status-final' : 'bg-neutral-bg text-ink-muted'
                        }`}
                    >
                        {configured ? t('aiProviders.configured') : t('aiProviders.notConfigured')}
                    </span>
                )}
                <span className="flex-1" />
                {!locked && (
                    <svg
                        width="16"
                        height="16"
                        viewBox="0 0 16 16"
                        fill="none"
                        className={`text-ink-muted transition-transform ${isExpanded ? 'rotate-180' : ''}`}
                    >
                        <path d="M4 6l4 4 4-4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                    </svg>
                )}
            </button>

            {/* Expanded form */}
            {isExpanded && !locked && (
                <form onSubmit={handleSave} className="border-t border-border-light pb-5 pl-[50px] pr-5 pt-1">
                    <div className="flex flex-col gap-5">
                        {setting.requires_api_key && (
                            <div className="flex flex-col gap-2">
                                <span className="text-[12px] font-medium uppercase tracking-[0.06em] text-ink-muted">
                                    {t('aiProviders.apiKey')}
                                </span>
                                {setting.has_api_key && !apiKey ? (
                                    <div className="flex items-center gap-3 rounded-md border border-border px-4 py-3">
                                        <span className="font-mono text-[13px] leading-[1.43] text-ink-muted">
                                            {setting.masked_api_key}
                                        </span>
                                        <span className="flex-1" />
                                        <button
                                            type="button"
                                            onClick={() => {
                                                fetch(deleteKey.url(setting.provider), {
                                                    method: 'DELETE',
                                                    headers: {
                                                        'X-XSRF-TOKEN': getXsrfToken(),
                                                        Accept: 'application/json',
                                                    },
                                                }).then(() => {
                                                    router.reload({ only: ['settings'] });
                                                });
                                            }}
                                            className="text-ink-muted transition-colors hover:text-danger"
                                        >
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                <path d="M3 6h18" />
                                                <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
                                                <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                                            </svg>
                                        </button>
                                    </div>
                                ) : (
                                    <Input
                                        type={apiKey || apiKeyFocused ? 'password' : 'text'}
                                        value={apiKey}
                                        onChange={(e) => setApiKey(e.target.value)}
                                        onFocus={() => setApiKeyFocused(true)}
                                        onBlur={() => setApiKeyFocused(false)}
                                        placeholder={t('aiProviders.apiKeyPlaceholder')}
                                        className="px-4 py-3"
                                    />
                                )}
                            </div>
                        )}

                        {setting.requires_base_url && (
                            <label className="flex flex-col gap-2">
                                <span className="text-[12px] font-medium uppercase tracking-[0.06em] text-ink-muted">
                                    {t('aiProviders.baseUrl')}
                                </span>
                                <Input
                                    type="url"
                                    value={baseUrl}
                                    onChange={(e) => setBaseUrl(e.target.value)}
                                    placeholder={isOllama ? 'http://localhost:11434' : 'https://your-resource.openai.azure.com'}
                                    className="px-4 py-3"
                                />
                            </label>
                        )}

                        {isAzure && (
                            <label className="flex flex-col gap-2">
                                <span className="text-[12px] font-medium uppercase tracking-[0.06em] text-ink-muted">
                                    {t('aiProviders.apiVersion')}
                                </span>
                                <Input
                                    type="text"
                                    value={apiVersion}
                                    onChange={(e) => setApiVersion(e.target.value)}
                                    placeholder="2024-10-21"
                                    className="px-4 py-3"
                                />
                            </label>
                        )}

                        {/* Advanced Settings */}
                        <div className="flex flex-col gap-4">
                            <button
                                type="button"
                                onClick={() => setAdvancedOpen(!advancedOpen)}
                                className="flex items-center gap-2"
                            >
                                <svg
                                    width="14"
                                    height="14"
                                    viewBox="0 0 16 16"
                                    fill="none"
                                    className={`text-ink-muted transition-transform ${advancedOpen ? '' : '-rotate-90'}`}
                                >
                                    <path d="M4 6l4 4 4-4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                                <span className="text-[13px] font-medium text-ink-muted">
                                    {t('aiProviders.advancedSettings')}
                                </span>
                            </button>

                            {advancedOpen && (
                                <div className="flex flex-col gap-4">
                                    <div className="h-px bg-border-light" />

                                    {/* Info banner */}
                                    <div className="flex gap-2.5 rounded-md bg-[#F5E6D3] px-3 py-2.5">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" className="mt-0.5 shrink-0 text-[#9A7B4F] opacity-80">
                                            <circle cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="2" />
                                            <path d="M12 16v-4M12 8h.01" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                                        </svg>
                                        <p className="text-[12px] leading-[1.5] text-[#7A5C32]">
                                            {t('aiProviders.advancedDescription')}
                                        </p>
                                    </div>

                                    {/* Model category fields */}
                                    <div className="flex flex-col gap-5">
                                        {/* Writing */}
                                        <div className="flex flex-col gap-1.5">
                                            <div className="flex flex-col gap-0.5">
                                                <span className="text-[13px] font-medium text-ink-muted">{t('aiProviders.writingModel')}</span>
                                                <span className="text-[11px] text-ink-faint">{t('aiProviders.writingModelDescription')}</span>
                                            </div>
                                            <Input
                                                type="text"
                                                value={writingModel}
                                                onChange={(e) => setWritingModel(e.target.value)}
                                                placeholder={t('aiProviders.modelPlaceholder')}
                                                className="px-4 py-3"
                                            />
                                            <span className="text-[11px] italic text-accent">{t('aiProviders.writingModelHint')}</span>
                                        </div>

                                        {/* Analysis */}
                                        <div className="flex flex-col gap-1.5">
                                            <div className="flex flex-col gap-0.5">
                                                <span className="text-[13px] font-medium text-ink-muted">{t('aiProviders.analysisModel')}</span>
                                                <span className="text-[11px] text-ink-faint">{t('aiProviders.analysisModelDescription')}</span>
                                            </div>
                                            <Input
                                                type="text"
                                                value={analysisModel}
                                                onChange={(e) => setAnalysisModel(e.target.value)}
                                                placeholder={t('aiProviders.modelPlaceholder')}
                                                className="px-4 py-3"
                                            />
                                        </div>

                                        {/* Extraction */}
                                        <div className="flex flex-col gap-1.5">
                                            <div className="flex flex-col gap-0.5">
                                                <span className="text-[13px] font-medium text-ink-muted">{t('aiProviders.extractionModel')}</span>
                                                <span className="text-[11px] text-ink-faint">{t('aiProviders.extractionModelDescription')}</span>
                                            </div>
                                            <Input
                                                type="text"
                                                value={extractionModel}
                                                onChange={(e) => setExtractionModel(e.target.value)}
                                                placeholder={t('aiProviders.modelPlaceholder')}
                                                className="px-4 py-3"
                                            />
                                            <span className="text-[11px] italic text-ink-faint">{t('aiProviders.extractionModelHint')}</span>
                                        </div>

                                        {/* Embedding model/dimensions (providers that support them) */}
                                        {setting.supports_embeddings && (
                                            <div className="flex gap-3">
                                                <div className="flex flex-1 flex-col gap-1.5">
                                                    <span className="text-[13px] font-medium text-ink-muted">
                                                        {isAzure ? t('aiProviders.embeddingDeployment') : t('aiProviders.embeddingModel')}
                                                    </span>
                                                    <Input
                                                        type="text"
                                                        value={embeddingModel}
                                                        onChange={(e) => setEmbeddingModel(e.target.value)}
                                                        placeholder={isAzure ? 'text-embedding-3-small' : t('aiProviders.modelPlaceholder')}
                                                        className="px-4 py-3"
                                                    />
                                                </div>
                                                <div className="flex w-32 flex-col gap-1.5">
                                                    <span className="text-[13px] font-medium text-ink-muted">{t('aiProviders.dimensions')}</span>
                                                    <Input
                                                        type="number"
                                                        value={embeddingDimensions}
                                                        onChange={(e) => setEmbeddingDimensions(e.target.value)}
                                                        placeholder="1536"
                                                        className="px-4 py-3"
                                                    />
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Buttons */}
                        <div className="flex items-center gap-3 pt-1">
                            <Button variant="primary" type="submit" disabled={saving} className="px-7 py-2.5 text-sm">
                                {saving ? t('aiProviders.saving') : t('aiProviders.save')}
                            </Button>
                            <Button variant="secondary" type="button" onClick={handleTest} disabled={testStatus.type === 'loading' || !configured} className="px-6 py-2.5 text-sm">
                                {testStatus.type === 'loading' ? t('aiProviders.testing') : t('aiProviders.testConnection')}
                            </Button>

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

    const enabledProvider = settings.find((s) => s.enabled)?.provider ?? null;
    const [expandedProvider, setExpandedProvider] = useState<string | null>(enabledProvider);

    const handleToggle = useCallback(
        (provider: string) => {
            if (locked) return;
            const willExpand = expandedProvider !== provider;
            setExpandedProvider(willExpand ? provider : null);
            if (willExpand && provider !== enabledProvider) {
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
            }
        },
        [locked, expandedProvider, enabledProvider],
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

                <div className={`overflow-hidden rounded-lg border border-border ${locked ? 'opacity-50' : ''}`}>
                    {settings.map((setting, i) => (
                        <div key={setting.provider} className={i > 0 ? 'border-t border-border' : ''}>
                            <ProviderCard
                                setting={setting}
                                locked={locked}
                                isSelected={setting.enabled}
                                isExpanded={expandedProvider === setting.provider}
                                onToggle={() => handleToggle(setting.provider)}
                            />
                        </div>
                    ))}
                </div>
            </div>
        </SettingsLayout>
    );
}
