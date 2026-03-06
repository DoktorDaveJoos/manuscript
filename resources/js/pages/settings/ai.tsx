import { AiSetting, License } from '@/types/models';
import { update, test as testConnection } from '@/actions/App/Http/Controllers/AiSettingsController';
import { activate, deactivate } from '@/actions/App/Http/Controllers/LicenseController';
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

function LicenseCard() {
    const { license } = usePage<{ license: License }>().props;
    const [key, setKey] = useState('');
    const [activating, setActivating] = useState(false);
    const [error, setError] = useState('');

    const handleActivate = useCallback(
        (e: FormEvent) => {
            e.preventDefault();
            setActivating(true);
            setError('');

            fetch(activate.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({ license_key: key }),
            })
                .then(async (res) => {
                    const json = await res.json();
                    if (!res.ok) {
                        setError(json.message || 'Invalid license key.');
                        return;
                    }
                    setKey('');
                    router.reload();
                })
                .catch(() => setError('Failed to activate license.'))
                .finally(() => setActivating(false));
        },
        [key],
    );

    const handleDeactivate = useCallback(() => {
        fetch(deactivate.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
                Accept: 'application/json',
            },
        }).then(() => router.reload());
    }, []);

    if (license.active) {
        return (
            <div className="flex items-center gap-3 rounded-md border border-border-light bg-surface px-4 py-3">
                <span className="text-[#2E7D32]">●</span>
                <span className="text-[14px] font-medium text-ink">License active</span>
                <span className="text-[13px] text-ink-muted">{license.masked_key}</span>
                <button
                    type="button"
                    onClick={handleDeactivate}
                    className="ml-auto text-[13px] text-accent transition-colors hover:opacity-80"
                >
                    Deactivate
                </button>
            </div>
        );
    }

    return (
        <div>
            <h2 className="text-[15px] font-medium text-ink">Manuscript License</h2>
            <p className="mt-1 text-[13px] text-ink-muted">
                Enter your license key to unlock AI features, Canvas, and more.
            </p>
            <form onSubmit={handleActivate} className="mt-4">
                <span className="mb-1.5 block text-[11px] font-medium uppercase tracking-[0.08em] text-ink-faint">
                    License Key
                </span>
                <div className="flex items-start gap-3">
                    <div className="flex flex-1 flex-col gap-1">
                        <input
                            type="text"
                            value={key}
                            onChange={(e) => setKey(e.target.value)}
                            placeholder="MANU.XXXXXXXX.…"
                            className="h-9 rounded-md border border-border bg-surface px-3 font-mono text-[13px] text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none"
                        />
                        {error && <span className="text-[12px] text-danger">{error}</span>}
                    </div>
                    <button
                        type="submit"
                        disabled={activating || !key}
                        className="h-9 rounded-md bg-accent px-4 text-[13px] font-medium text-white transition-opacity hover:opacity-90 disabled:opacity-50"
                    >
                        {activating ? 'Activating...' : 'Activate'}
                    </button>
                </div>
            </form>
        </div>
    );
}

function ProviderCard({ setting, locked }: { setting: ProviderSetting; locked: boolean }) {
    const [enabled, setEnabled] = useState(setting.enabled);
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

    const handleToggle = useCallback(() => {
        if (locked) return;
        const newEnabled = !enabled;
        setEnabled(newEnabled);

        fetch(update.url(setting.provider), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify({ enabled: newEnabled }),
        }).catch(() => {
            setEnabled(!newEnabled);
        });
    }, [enabled, setting.provider, locked]);

    const handleSave = useCallback(
        (e: FormEvent) => {
            e.preventDefault();
            setSaving(true);
            setSaveMessage('');

            const data: Record<string, unknown> = { enabled };
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
                .catch(() => setSaveMessage('Failed to save settings.'))
                .finally(() => setSaving(false));
        },
        [enabled, apiKey, baseUrl, apiVersion, textModel, embeddingModel, embeddingDimensions, setting.provider],
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
                setTestStatus({ type: 'error', message: 'Connection test failed.' });
                setTimeout(() => setTestStatus({ type: 'idle' }), 5000);
            });
    }, [setting.provider]);

    const configured = setting.has_api_key || !setting.requires_api_key;

    return (
        <div className={`rounded-lg border border-border bg-white ${locked ? 'opacity-50' : ''}`}>
            {/* Header row */}
            <div className="flex items-center justify-between px-5 py-4">
                <div className="flex items-center gap-3">
                    {locked && (
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" className="text-ink-faint">
                            <rect x="3" y="7" width="10" height="7" rx="1.5" stroke="currentColor" strokeWidth="1.5" />
                            <path d="M5 7V5a3 3 0 016 0v2" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                        </svg>
                    )}
                    <span className="text-[15px] font-medium text-ink">{setting.label}</span>
                    {!locked && (
                        <span
                            className={`rounded-full px-2 py-0.5 text-[11px] font-medium ${
                                configured ? 'bg-[#E8F5E9] text-[#2E7D32]' : 'bg-neutral-bg text-ink-muted'
                            }`}
                        >
                            {configured ? 'Configured' : 'Not configured'}
                        </span>
                    )}
                </div>
                <button
                    type="button"
                    role="switch"
                    aria-checked={enabled}
                    onClick={handleToggle}
                    disabled={locked}
                    className={`relative inline-flex h-[22px] w-[40px] shrink-0 items-center rounded-full transition-colors ${
                        enabled && !locked ? 'bg-accent' : 'bg-[#D4D1CA]'
                    }`}
                >
                    <span
                        className={`inline-block h-[18px] w-[18px] rounded-full bg-white shadow-sm transition-transform ${
                            enabled && !locked ? 'translate-x-[20px]' : 'translate-x-[2px]'
                        }`}
                    />
                </button>
            </div>

            {/* Expanded form when enabled and not locked */}
            {enabled && !locked && (
                <form onSubmit={handleSave} className="border-t border-border-light px-5 pb-5 pt-4">
                    <div className="flex flex-col gap-4">
                        {setting.requires_api_key && (
                            <label className="flex flex-col gap-1.5">
                                <span className="text-[13px] font-medium text-ink-muted">API Key</span>
                                <input
                                    type="password"
                                    value={apiKey}
                                    onChange={(e) => setApiKey(e.target.value)}
                                    placeholder={setting.has_api_key ? '••••••••••••••••' : 'Enter API key'}
                                    className="h-9 rounded-md border border-border bg-surface px-3 text-[13px] text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none"
                                />
                            </label>
                        )}

                        {setting.requires_base_url && (
                            <label className="flex flex-col gap-1.5">
                                <span className="text-[13px] font-medium text-ink-muted">Base URL</span>
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
                                <span className="text-[13px] font-medium text-ink-muted">API Version</span>
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
                                {isAzure ? 'Deployment Name' : 'Text Model'}
                            </span>
                            <input
                                type="text"
                                value={textModel}
                                onChange={(e) => setTextModel(e.target.value)}
                                placeholder={isAzure ? 'gpt-4o' : 'Model identifier'}
                                className="h-9 rounded-md border border-border bg-surface px-3 text-[13px] text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none"
                            />
                        </label>

                        {setting.supports_embeddings && (
                            <div className="flex gap-3">
                                <label className="flex flex-1 flex-col gap-1.5">
                                    <span className="text-[13px] font-medium text-ink-muted">
                                        {isAzure ? 'Embedding Deployment' : 'Embedding Model'}
                                    </span>
                                    <input
                                        type="text"
                                        value={embeddingModel}
                                        onChange={(e) => setEmbeddingModel(e.target.value)}
                                        placeholder={isAzure ? 'text-embedding-3-small' : 'Model identifier'}
                                        className="h-9 rounded-md border border-border bg-surface px-3 text-[13px] text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none"
                                    />
                                </label>
                                <label className="flex w-32 flex-col gap-1.5">
                                    <span className="text-[13px] font-medium text-ink-muted">Dimensions</span>
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
                                className="h-8 rounded-md bg-ink px-3.5 text-[13px] font-medium text-white transition-opacity hover:opacity-90 disabled:opacity-50"
                            >
                                {saving ? 'Saving...' : 'Save'}
                            </button>
                            <button
                                type="button"
                                onClick={handleTest}
                                disabled={testStatus.type === 'loading' || !configured}
                                className="h-8 rounded-md border border-border px-3.5 text-[13px] font-medium text-ink transition-colors hover:bg-neutral-bg disabled:opacity-50"
                            >
                                {testStatus.type === 'loading' ? 'Testing...' : 'Test connection'}
                            </button>

                            {saveMessage && (
                                <span className="text-[12px] font-medium text-[#2E7D32]">{saveMessage}</span>
                            )}
                            {testStatus.type === 'success' && (
                                <span className="text-[12px] font-medium text-[#2E7D32]">{testStatus.message}</span>
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

export default function AiSettings({ settings, book }: Props) {
    const { license } = usePage<{ license: License }>().props;
    const locked = !license.active;

    return (
        <SettingsLayout activeSection="license-ai" book={book} title="License & AI">
            <div className="flex flex-col gap-6">
                <div>
                    <h1 className="text-[22px] font-semibold tracking-[-0.01em] text-ink">License & AI</h1>
                    <p className="mt-1 text-[14px] text-ink-muted">
                        Manage your license and configure AI providers.
                    </p>
                </div>

                <LicenseCard />

                <div className="flex flex-col gap-3">
                    {settings.map((setting) => (
                        <ProviderCard key={setting.provider} setting={setting} locked={locked} />
                    ))}
                </div>
            </div>
        </SettingsLayout>
    );
}
