import { Head, Link, router, usePage } from '@inertiajs/react';
import { Check, Copy, Trash2 } from 'lucide-react';
import { useState, useCallback, useRef, useEffect } from 'react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import {
    update as updateAiProvider,
    deleteKey,
    test as testConnection,
} from '@/actions/App/Http/Controllers/AiSettingsController';
import { update } from '@/actions/App/Http/Controllers/AppSettingsController';
import {
    exportMethod as backupExport,
    importMethod as backupImport,
    revert as backupRevert,
} from '@/actions/App/Http/Controllers/BackupController';
import { index as booksIndex } from '@/actions/App/Http/Controllers/BookController';
import {
    activate,
    deactivate,
    revalidate,
} from '@/actions/App/Http/Controllers/LicenseController';
import {
    show as speechModelShow,
    store as speechModelStore,
    destroy as speechModelDestroy,
} from '@/actions/App/Http/Controllers/SpeechModelController';
import { DEFAULT_FONT_ID, FONTS } from '@/components/editor/FontSelector';
import {
    DEFAULT_FONT_SIZE,
    FONT_SIZES,
} from '@/components/editor/FontSizeSelector';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/Accordion';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/Alert';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import Dialog from '@/components/ui/Dialog';
import FormField from '@/components/ui/FormField';
import Input from '@/components/ui/Input';
import NavItem from '@/components/ui/NavItem';
import PageHeader from '@/components/ui/PageHeader';
import { RadioGroup, RadioGroupItem } from '@/components/ui/RadioGroup';
import SectionLabel from '@/components/ui/SectionLabel';
import Toggle from '@/components/ui/Toggle';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/ToggleGroup';
import { useAutoUpdater } from '@/hooks/useAutoUpdater';
import { useTheme } from '@/hooks/useTheme';
import { setAppLanguage } from '@/i18n';
import { setAnalyticsEnabled } from '@/lib/analytics';
import type { Theme } from '@/lib/theme';
import { jsonFetchHeaders, saveAppSetting } from '@/lib/utils';
import type {
    AppSettings,
    AiProvider,
    AiSetting,
    EditorTextPosition,
    License,
} from '@/types/models';

type ProviderSetting = AiSetting & {
    label: string;
};

interface Props {
    settings: AppSettings;
    ai_providers: ProviderSetting[];
    version: string;
    backup: {
        has_rollback: boolean;
        last_export_at: string | null;
    };
}

const THEME_OPTIONS = [
    {
        value: 'light' as Theme,
        labelKey: 'appearance.theme.light' as const,
        descriptionKey: 'appearance.theme.lightDescription' as const,
    },
    {
        value: 'dark' as Theme,
        labelKey: 'appearance.theme.dark' as const,
        descriptionKey: 'appearance.theme.darkDescription' as const,
    },
    {
        value: 'system' as Theme,
        labelKey: 'appearance.theme.system' as const,
        descriptionKey: 'appearance.theme.systemDescription' as const,
    },
];

const LOCALES = ['en', 'de', 'es'] as const;

// ─── License Section ─────────────────────────────────────────────────

function LicenseSection() {
    const { t } = useTranslation('settings');
    const { license } = usePage<{ license: License }>().props;
    const [key, setKey] = useState('');
    const [activating, setActivating] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        if (!license.active) return;
        const controller = new AbortController();
        fetch(revalidate.url(), {
            method: 'POST',
            headers: jsonFetchHeaders(),
            signal: controller.signal,
        }).catch(() => {});
        return () => controller.abort();
    }, [license.active]);

    const handleActivate = useCallback(
        (e: FormEvent) => {
            e.preventDefault();
            setActivating(true);
            setError('');
            fetch(activate.url(), {
                method: 'POST',
                headers: jsonFetchHeaders(),
                body: JSON.stringify({ license_key: key }),
            })
                .then(async (res) => {
                    const json = await res.json();
                    if (!res.ok) {
                        setError(
                            res.status === 503
                                ? t('license.error.network')
                                : json.message || t('license.error.invalid'),
                        );
                        return;
                    }
                    setKey('');
                    router.reload({ only: ['license'] });
                })
                .catch(() => setError(t('license.error.network')))
                .finally(() => setActivating(false));
        },
        [key, t],
    );

    const handleDeactivate = useCallback(() => {
        setError('');
        fetch(deactivate.url(), {
            method: 'POST',
            headers: jsonFetchHeaders(),
        })
            .then(async (res) => {
                if (!res.ok) {
                    const json = await res.json();
                    setError(
                        res.status === 503
                            ? t('license.error.network')
                            : json.message || t('license.error.failed'),
                    );
                    return;
                }
                router.reload({ only: ['license'] });
            })
            .catch(() => setError(t('license.error.network')));
    }, [t]);

    return (
        <div>
            <SectionLabel variant="section">
                {t('section.license')}
            </SectionLabel>
            <Card className="mt-3">
                {license.active ? (
                    <>
                        <div className="flex items-center justify-between px-6 py-[18px]">
                            <div className="flex items-center gap-2.5">
                                <span className="inline-block h-2 w-2 rounded-full bg-status-final" />
                                <span className="text-[14px] font-medium text-ink">
                                    {t('license.active')}
                                </span>
                                <span className="text-[13px] text-ink-muted">
                                    {license.masked_key}
                                </span>
                            </div>
                            <button
                                type="button"
                                onClick={handleDeactivate}
                                className="text-[13px] font-medium text-accent transition-colors hover:opacity-80"
                            >
                                {t('license.deactivate')}
                            </button>
                        </div>
                        <div className="border-t border-border" />
                        <div className="flex items-center gap-2.5 px-6 py-3.5">
                            <span className="text-[14px] font-medium text-ink">
                                Pro
                            </span>
                            <Badge variant="default">Lifetime</Badge>
                        </div>
                        {error && (
                            <div className="px-6 pb-3">
                                <span className="text-[12px] text-danger">
                                    {error}
                                </span>
                            </div>
                        )}
                    </>
                ) : (
                    <div className="p-6">
                        <h2 className="text-sm font-medium text-ink">
                            {t('license.formTitle')}
                        </h2>
                        <p className="mt-1 text-[13px] text-ink-muted">
                            {t('license.formDescription')}
                        </p>
                        <form onSubmit={handleActivate} className="mt-4">
                            <FormField
                                label={t('license.keyLabel')}
                                error={error || undefined}
                            >
                                <div className="flex items-start gap-3">
                                    <Input
                                        type="text"
                                        value={key}
                                        onChange={(e) => setKey(e.target.value)}
                                        placeholder={t(
                                            'license.keyPlaceholder',
                                        )}
                                        className="font-mono"
                                    />
                                    <Button
                                        variant="primary"
                                        type="submit"
                                        disabled={activating || !key}
                                        className="h-9"
                                    >
                                        {activating
                                            ? t('license.activating')
                                            : t('license.activate')}
                                    </Button>
                                </div>
                            </FormField>
                        </form>
                    </div>
                )}
            </Card>
        </div>
    );
}

// ─── Language Section ─────────────────────────────────────────────────

function LanguageSection() {
    const { t, i18n } = useTranslation('settings');
    const { app_settings } = usePage<{ app_settings: AppSettings }>().props;
    const activeLocale = i18n.language || app_settings.locale || 'en';

    function switchLocale(locale: string) {
        if (locale === activeLocale) return;
        void setAppLanguage(locale);
        saveAppSetting('locale', locale);
    }

    return (
        <div>
            <SectionLabel variant="section">
                {t('language.sectionLabel')}
            </SectionLabel>
            <Card className="mt-3 p-6">
                <div className="flex flex-col gap-4">
                    <div>
                        <span className="text-sm font-medium text-ink">
                            {t('language.title')}
                        </span>
                        <p className="mt-1 text-[13px] text-ink-muted">
                            {t('language.description')}
                        </p>
                    </div>
                    <ToggleGroup
                        type="single"
                        value={activeLocale}
                        onValueChange={(val) => {
                            if (val) switchLocale(val);
                        }}
                    >
                        {LOCALES.map((locale) => (
                            <ToggleGroupItem key={locale} value={locale}>
                                {
                                    {
                                        en: 'English',
                                        de: 'Deutsch',
                                        es: 'Español',
                                    }[locale]
                                }
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                </div>
            </Card>
        </div>
    );
}

// ─── Appearance Section ──────────────────────────────────────────────

function AppearanceSection() {
    const { t } = useTranslation('settings');
    const { theme, setTheme } = useTheme();

    return (
        <div>
            <SectionLabel variant="section">
                {t('appearance.title')}
            </SectionLabel>
            <Card className="mt-3 p-6">
                <div className="flex flex-col gap-4">
                    <div>
                        <span className="text-sm font-medium text-ink">
                            {t('appearance.theme.title')}
                        </span>
                        <p className="mt-1 text-[13px] text-ink-muted">
                            {t('appearance.theme.description')}
                        </p>
                    </div>
                    <RadioGroup
                        value={theme}
                        onValueChange={(val) => setTheme(val as Theme)}
                    >
                        {THEME_OPTIONS.map((option) => (
                            <label
                                key={option.value}
                                htmlFor={`theme-${option.value}`}
                                className="flex cursor-pointer items-start gap-3 rounded-lg px-3 py-2.5 transition-colors hover:bg-neutral-bg"
                            >
                                <RadioGroupItem
                                    value={option.value}
                                    id={`theme-${option.value}`}
                                    className="mt-0.5"
                                />
                                <div className="flex flex-col">
                                    <span className="text-sm font-medium text-ink">
                                        {t(option.labelKey)}
                                    </span>
                                    <span className="text-xs text-ink-muted">
                                        {t(option.descriptionKey)}
                                    </span>
                                </div>
                            </label>
                        ))}
                    </RadioGroup>
                </div>
            </Card>
        </div>
    );
}

// ─── Editor Section ──────────────────────────────────────────────────

function EditorSection({
    settings,
    saveSetting,
}: {
    settings: AppSettings;
    saveSetting: (key: string, value: boolean | string | number) => void;
}) {
    const { t } = useTranslation('settings');
    const [hideToolbar, setHideToolbar] = useState(
        settings.hide_formatting_toolbar,
    );
    const [showAi, setShowAi] = useState(settings.show_ai_features);
    const [editorFont, setEditorFont] = useState(
        settings.editor_font ?? DEFAULT_FONT_ID,
    );
    const [editorFontSize, setEditorFontSize] = useState(
        Number(settings.editor_font_size) || DEFAULT_FONT_SIZE,
    );
    const [editorTextPosition, setEditorTextPosition] = useState(
        settings.editor_text_position ?? 'center',
    );

    return (
        <div>
            <SectionLabel variant="section">
                {t('appearance.editor')}
            </SectionLabel>
            <div className="mt-3 flex flex-col gap-3">
                {/* Font */}
                <Card className="px-6 py-3.5">
                    <div className="flex items-center justify-between">
                        <div>
                            <span className="text-[14px] font-medium text-ink">
                                {t('appearance.editorFont.label')}
                            </span>
                            <p className="mt-0.5 text-[13px] text-ink-muted">
                                {t('appearance.editorFont.description')}
                            </p>
                        </div>
                    </div>
                    <ToggleGroup
                        className="mt-3"
                        type="single"
                        value={editorFont}
                        onValueChange={(val) => {
                            if (val) {
                                setEditorFont(val);
                                saveSetting('editor_font', val);
                            }
                        }}
                    >
                        {FONTS.map((font) => (
                            <ToggleGroupItem
                                key={font.id}
                                value={font.id}
                                style={{ fontFamily: font.family }}
                            >
                                {font.label}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                </Card>

                {/* Font size */}
                <Card className="flex items-center justify-between px-6 py-3.5">
                    <div>
                        <span className="text-[14px] font-medium text-ink">
                            {t('appearance.editorFontSize.label')}
                        </span>
                        <p className="mt-0.5 text-[13px] text-ink-muted">
                            {t('appearance.editorFontSize.description')}
                        </p>
                    </div>
                    <ToggleGroup
                        type="single"
                        value={String(editorFontSize)}
                        onValueChange={(val) => {
                            if (val) {
                                const size = Number(val);
                                setEditorFontSize(size);
                                saveSetting('editor_font_size', size);
                            }
                        }}
                    >
                        {FONT_SIZES.map((size) => (
                            <ToggleGroupItem
                                key={size}
                                value={String(size)}
                                className="flex size-8 items-center justify-center"
                            >
                                {size}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                </Card>

                {/* Text position in view */}
                <Card className="flex items-center justify-between px-6 py-3.5">
                    <div>
                        <span className="text-[14px] font-medium text-ink">
                            {t('appearance.editorTextPosition.label')}
                        </span>
                        <p className="mt-0.5 text-[13px] text-ink-muted">
                            {t('appearance.editorTextPosition.description')}
                        </p>
                    </div>
                    <ToggleGroup
                        type="single"
                        value={editorTextPosition}
                        onValueChange={(val) => {
                            if (val) {
                                setEditorTextPosition(
                                    val as EditorTextPosition,
                                );
                                saveSetting('editor_text_position', val);
                            }
                        }}
                    >
                        {(['left', 'center', 'right'] as const).map((pos) => (
                            <ToggleGroupItem key={pos} value={pos}>
                                {t(`appearance.editorTextPosition.${pos}`)}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                </Card>

                <Card className="flex items-center justify-between px-6 py-3.5">
                    <div>
                        <span className="text-[14px] font-medium text-ink">
                            {t('appearance.hideToolbar.label')}
                        </span>
                        <p className="mt-0.5 text-[13px] text-ink-muted">
                            {t('appearance.hideToolbar.description')}
                        </p>
                    </div>
                    <Toggle
                        checked={hideToolbar}
                        onChange={() => {
                            const next = !hideToolbar;
                            setHideToolbar(next);
                            saveSetting('hide_formatting_toolbar', next);
                        }}
                    />
                </Card>
                <Card className="flex items-center justify-between px-6 py-3.5">
                    <div>
                        <span className="text-[14px] font-medium text-ink">
                            {t('appearance.showAi.label')}
                        </span>
                        <p className="mt-0.5 text-[13px] text-ink-muted">
                            {t('appearance.showAi.description')}
                        </p>
                    </div>
                    <Toggle
                        checked={showAi}
                        onChange={() => {
                            const next = !showAi;
                            setShowAi(next);
                            saveSetting('show_ai_features', next);
                        }}
                    />
                </Card>
            </div>
        </div>
    );
}

// ─── AI Providers Section ────────────────────────────────────────────

type TestStatus =
    | { type: 'idle' }
    | { type: 'loading' }
    | { type: 'success'; message: string }
    | { type: 'error'; message: string };

function ProviderForm({ setting }: { setting: ProviderSetting }) {
    const { t } = useTranslation('settings');
    const [apiKey, setApiKey] = useState('');
    const [saving, setSaving] = useState(false);
    const [testStatus, setTestStatus] = useState<TestStatus>({ type: 'idle' });
    const [saveMessage, setSaveMessage] = useState('');
    const [guideOpen, setGuideOpen] = useState(false);
    const configured = setting.has_api_key;

    const handleSave = useCallback(
        (e: FormEvent) => {
            e.preventDefault();
            setSaving(true);
            setSaveMessage('');
            const data: Record<string, unknown> = { enabled: true };
            if (apiKey) data.api_key = apiKey;

            fetch(updateAiProvider.url(setting.provider), {
                method: 'PUT',
                headers: jsonFetchHeaders(),
                body: JSON.stringify(data),
            })
                .then(async (res) => {
                    if (!res.ok) throw new Error('Save failed');
                    const json = await res.json();
                    setSaveMessage(json.message);
                    setApiKey('');
                    router.reload({ only: ['ai_providers'] });
                    setTimeout(() => setSaveMessage(''), 3000);
                })
                .catch(() => setSaveMessage(t('aiProviders.saveFailed')))
                .finally(() => setSaving(false));
        },
        [apiKey, setting.provider, t],
    );

    const handleTest = useCallback(() => {
        setTestStatus({ type: 'loading' });
        fetch(testConnection.url(setting.provider), {
            method: 'POST',
            headers: jsonFetchHeaders(),
        })
            .then(async (res) => {
                const json = await res.json();
                if (json.success) {
                    setTestStatus({ type: 'success', message: json.message });
                    setTimeout(() => setTestStatus({ type: 'idle' }), 5000);
                    return;
                }
                // Actionable failures (no credits, bad key, …) get a precise
                // localized explanation and stay visible until the next test.
                setTestStatus({
                    type: 'error',
                    message: isExplainedTestFailure(json.kind)
                        ? t(`aiProviders.testResult.${json.kind}`)
                        : json.message || t('aiProviders.testFailed'),
                });
            })
            .catch(() => {
                setTestStatus({
                    type: 'error',
                    message: t('aiProviders.testFailed'),
                });
            });
    }, [setting.provider, t]);

    return (
        <>
            <form onSubmit={handleSave} className="pb-1">
                <div className="flex flex-col gap-5 pl-[30px]">
                    {setting.api_key_recovery_needed && (
                        <Alert variant="destructive">
                            <AlertTitle>
                                {t('aiProviders.keyRecovery.title')}
                            </AlertTitle>
                            <AlertDescription>
                                {t('aiProviders.keyRecovery.description')}
                            </AlertDescription>
                        </Alert>
                    )}
                    <FormField
                        label={t('aiProviders.apiKey')}
                        action={
                            <button
                                type="button"
                                onClick={() => setGuideOpen(true)}
                                className="text-[12px] font-medium text-ink-muted transition-colors hover:text-ink"
                            >
                                {t('aiProviders.howToGetKey.trigger')}
                            </button>
                        }
                    >
                        {setting.has_api_key && !apiKey ? (
                            <div className="flex items-center justify-between gap-3 rounded-md border border-border px-4 py-2.5">
                                <span className="font-mono text-[13px] leading-[1.43] text-ink-muted">
                                    {setting.masked_api_key}
                                </span>
                                <button
                                    type="button"
                                    onClick={() => {
                                        fetch(deleteKey.url(setting.provider), {
                                            method: 'DELETE',
                                            headers: jsonFetchHeaders(),
                                        })
                                            .then((res) => {
                                                if (!res.ok) throw new Error();
                                                router.reload({
                                                    only: ['ai_providers'],
                                                });
                                            })
                                            .catch(() =>
                                                setSaveMessage(
                                                    t('aiProviders.saveFailed'),
                                                ),
                                            );
                                    }}
                                    className="text-ink-muted transition-colors hover:text-danger"
                                >
                                    <Trash2 size={16} />
                                </button>
                            </div>
                        ) : (
                            <Input
                                type="password"
                                value={apiKey}
                                onChange={(e) => setApiKey(e.target.value)}
                                placeholder={t('aiProviders.apiKeyPlaceholder')}
                            />
                        )}
                    </FormField>

                    <div className="flex items-center gap-3 pt-1">
                        <Button
                            variant="primary"
                            size="lg"
                            type="submit"
                            disabled={saving}
                        >
                            {saving
                                ? t('aiProviders.saving')
                                : t('aiProviders.save')}
                        </Button>
                        <Button
                            variant="secondary"
                            size="lg"
                            type="button"
                            onClick={handleTest}
                            disabled={
                                testStatus.type === 'loading' || !configured
                            }
                        >
                            {testStatus.type === 'loading'
                                ? t('aiProviders.testing')
                                : t('aiProviders.testConnection')}
                        </Button>
                        {saveMessage && (
                            <span className="text-[12px] font-medium text-status-final">
                                {saveMessage}
                            </span>
                        )}
                        {testStatus.type === 'success' && (
                            <span className="text-[12px] font-medium text-status-final">
                                {testStatus.message}
                            </span>
                        )}
                    </div>
                    {testStatus.type === 'error' && (
                        <p className="text-[12px] leading-[1.5] font-medium text-danger">
                            {testStatus.message}
                        </p>
                    )}
                </div>
            </form>
            {guideOpen && (
                <GetApiKeyDialog
                    provider={setting.provider}
                    providerLabel={setting.label}
                    onClose={() => setGuideOpen(false)}
                />
            )}
        </>
    );
}

// Test failures that have a dedicated, actionable explanation in the
// settings locale files (aiProviders.testResult.*). Anything else falls
// back to the provider's raw error message.
const EXPLAINED_TEST_FAILURES = [
    'invalid_key',
    'insufficient_credits',
    'rate_limited',
    'overloaded',
    'timeout',
] as const;

function isExplainedTestFailure(
    kind: unknown,
): kind is (typeof EXPLAINED_TEST_FAILURES)[number] {
    return (
        typeof kind === 'string' &&
        (EXPLAINED_TEST_FAILURES as readonly string[]).includes(kind)
    );
}

const PROVIDER_CONSOLE_URLS: Partial<Record<AiProvider, string>> = {
    anthropic: 'https://console.anthropic.com',
    openai: 'https://platform.openai.com/api-keys',
    gemini: 'https://aistudio.google.com/apikey',
    groq: 'https://console.groq.com/keys',
    xai: 'https://console.x.ai',
    deepseek: 'https://platform.deepseek.com/api_keys',
    mistral: 'https://console.mistral.ai/api-keys',
    openrouter: 'https://openrouter.ai/keys',
};

function GetApiKeyDialog({
    provider,
    providerLabel,
    onClose,
}: {
    provider: AiProvider;
    providerLabel: string;
    onClose: () => void;
}) {
    const { t } = useTranslation('settings');
    const [copied, setCopied] = useState(false);
    const titleText = t('aiProviders.howToGetKey.title', {
        provider: providerLabel,
    });
    const rawSteps = t(`aiProviders.howToGetKey.${provider}.steps`, {
        returnObjects: true,
    });
    const steps = Array.isArray(rawSteps) ? (rawSteps as string[]) : [];
    const consoleUrl = PROVIDER_CONSOLE_URLS[provider] ?? '';

    const handleCopy = useCallback(() => {
        navigator.clipboard.writeText(consoleUrl).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    }, [consoleUrl]);

    return (
        <Dialog onClose={onClose} title={titleText} width={520}>
            <h3 className="font-serif text-2xl leading-8 font-semibold tracking-[-0.01em] text-ink">
                {titleText}
            </h3>
            <p className="mt-3 text-[13px] text-ink-muted">
                {t('aiProviders.howToGetKey.intro', {
                    provider: providerLabel,
                })}
            </p>
            <ol className="mt-6 flex flex-col gap-4">
                {steps.map((step, i) => (
                    <li key={i} className="flex gap-3">
                        <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-neutral-bg text-[12px] font-semibold text-ink">
                            {i + 1}
                        </span>
                        <span className="pt-0.5 text-sm leading-[1.5] text-ink">
                            {step}
                        </span>
                    </li>
                ))}
            </ol>
            <div className="mt-7 rounded-lg border border-border-light bg-neutral-bg p-4">
                <p className="text-[12px] leading-[1.5] text-ink-muted">
                    {t('aiProviders.howToGetKey.copyHint')}
                </p>
                <div className="mt-2.5 flex items-center gap-2">
                    <code className="min-w-0 flex-1 truncate font-mono text-[13px] text-ink">
                        {consoleUrl}
                    </code>
                    <Button
                        type="button"
                        variant="primary"
                        size="sm"
                        onClick={handleCopy}
                    >
                        {copied ? <Check size={14} /> : <Copy size={14} />}
                        {copied
                            ? t('aiProviders.howToGetKey.copied')
                            : t('aiProviders.howToGetKey.copy')}
                    </Button>
                </div>
            </div>
            <div className="mt-5 flex items-center justify-end">
                <Button type="button" variant="secondary" onClick={onClose}>
                    {t('aiProviders.howToGetKey.close')}
                </Button>
            </div>
        </Dialog>
    );
}

function AiProvidersSection({ providers }: { providers: ProviderSetting[] }) {
    const { t } = useTranslation('settings');
    const enabledProvider = providers.find((p) => p.enabled)?.provider ?? null;

    const handleSelect = useCallback((provider: string) => {
        fetch(updateAiProvider.url(provider), {
            method: 'PUT',
            headers: jsonFetchHeaders(),
            body: JSON.stringify({ enabled: true }),
        }).then(() => {
            router.reload({ only: ['ai_providers'] });
        });
    }, []);

    const handleAccordionChange = useCallback(
        (value: string) => {
            if (value && value !== enabledProvider) {
                handleSelect(value);
            }
        },
        [enabledProvider, handleSelect],
    );

    return (
        <div>
            <SectionLabel variant="section">
                {t('aiProviders.title')}
            </SectionLabel>
            <Card className="mt-3 px-5">
                <Accordion
                    type="single"
                    collapsible
                    defaultValue={enabledProvider ?? undefined}
                    onValueChange={handleAccordionChange}
                >
                    {providers.map((setting) => {
                        const isSelected = setting.enabled;
                        const configured = setting.has_api_key;

                        return (
                            <AccordionItem
                                key={setting.provider}
                                value={setting.provider}
                            >
                                <AccordionTrigger className="px-0 py-4">
                                    <div className="flex flex-1 items-center gap-3">
                                        <span
                                            className={`flex size-[18px] items-center justify-center rounded-full border-2 transition-colors ${isSelected ? 'border-ink' : 'border-border'}`}
                                        >
                                            {isSelected && (
                                                <span className="size-[10px] rounded-full bg-ink" />
                                            )}
                                        </span>
                                        <span
                                            className={`text-sm ${isSelected ? 'font-medium' : ''} text-ink`}
                                        >
                                            {setting.label}
                                        </span>
                                        <span className="flex-1" />
                                        <Badge
                                            className="mr-2"
                                            variant={
                                                configured
                                                    ? 'success'
                                                    : 'secondary'
                                            }
                                        >
                                            {configured
                                                ? t('aiProviders.configured')
                                                : t(
                                                      'aiProviders.notConfigured',
                                                  )}
                                        </Badge>
                                    </div>
                                </AccordionTrigger>
                                <AccordionContent>
                                    <ProviderForm setting={setting} />
                                </AccordionContent>
                            </AccordionItem>
                        );
                    })}
                </Accordion>
            </Card>
        </div>
    );
}

// ─── Speech Input Section ────────────────────────────────────────────

type SpeechModelStatus = {
    state: 'missing' | 'downloading' | 'error' | 'ready';
    variant: string;
    label: string;
    size_bytes: number;
    progress?: number;
    error?: string;
};

function SpeechInputSection() {
    const { t } = useTranslation('settings');
    const [status, setStatus] = useState<SpeechModelStatus | null>(null);

    useEffect(() => {
        const controller = new AbortController();
        fetch(speechModelShow.url(), {
            headers: jsonFetchHeaders(),
            signal: controller.signal,
        })
            .then((response) => (response.ok ? response.json() : null))
            .then((next) => {
                if (next) setStatus(next as SpeechModelStatus);
            })
            .catch(() => {});
        return () => controller.abort();
    }, []);

    // Keep the progress bar live while the queued download runs.
    useEffect(() => {
        if (status?.state !== 'downloading') return;
        const id = setInterval(() => {
            fetch(speechModelShow.url(), { headers: jsonFetchHeaders() })
                .then((response) => (response.ok ? response.json() : null))
                .then((next) => {
                    if (next) setStatus(next as SpeechModelStatus);
                })
                .catch(() => {});
        }, 1500);
        return () => clearInterval(id);
    }, [status?.state]);

    const startDownload = useCallback(() => {
        fetch(speechModelStore.url(), {
            method: 'POST',
            headers: jsonFetchHeaders(),
        })
            .then((response) => response.json())
            .then((next) => setStatus(next as SpeechModelStatus))
            .catch(() => {});
    }, []);

    const removeModel = useCallback(() => {
        fetch(speechModelDestroy.url(), {
            method: 'DELETE',
            headers: jsonFetchHeaders(),
        })
            .then((response) => response.json())
            .then((next) => setStatus(next as SpeechModelStatus))
            .catch(() => {});
    }, []);

    const sizeLabel = status ? `${Math.round(status.size_bytes / 1e6)} MB` : '';

    return (
        <div>
            <SectionLabel variant="section">
                {t('speech.sectionLabel')}
            </SectionLabel>
            <Card className="mt-3 p-6">
                <h3 className="text-sm font-medium text-ink">
                    {t('speech.title')}
                </h3>
                <p className="mt-1 text-[13px] leading-relaxed text-ink-muted">
                    {t('speech.description')}
                </p>
                {status && (
                    <div className="mt-4">
                        {status.state === 'missing' && (
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={startDownload}
                            >
                                {t('speech.download', { size: sizeLabel })}
                            </Button>
                        )}
                        {status.state === 'downloading' && (
                            <div>
                                <div className="flex items-center justify-between text-xs text-ink-muted">
                                    <span>{t('speech.downloading')}</span>
                                    <span>{status.progress ?? 0}%</span>
                                </div>
                                <div className="mt-2 h-1.5 w-full overflow-hidden rounded bg-neutral-bg">
                                    <div
                                        className="h-full rounded bg-ink transition-[width] duration-500"
                                        style={{
                                            width: `${status.progress ?? 0}%`,
                                        }}
                                    />
                                </div>
                            </div>
                        )}
                        {status.state === 'error' && (
                            <div className="flex flex-col gap-3">
                                <Alert variant="destructive">
                                    <AlertDescription>
                                        {status.error ??
                                            t('speech.errorFallback')}
                                    </AlertDescription>
                                </Alert>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    className="self-start"
                                    onClick={startDownload}
                                >
                                    {t('speech.retry')}
                                </Button>
                            </div>
                        )}
                        {status.state === 'ready' && (
                            <div className="flex items-center justify-between">
                                <span className="flex items-center gap-2 text-[13px] text-ink">
                                    <Check className="size-4 text-ai-green" />
                                    {t('speech.ready', { size: sizeLabel })}
                                </span>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="text-ink-muted hover:text-delete"
                                    onClick={removeModel}
                                >
                                    <Trash2 className="size-3.5" />
                                    {t('speech.delete')}
                                </Button>
                            </div>
                        )}
                    </div>
                )}
                <p className="mt-4 text-xs text-ink-faint">
                    {t('speech.hint')}
                </p>
            </Card>
        </div>
    );
}

// ─── Privacy Section ─────────────────────────────────────────────────

function PrivacySection({
    settings,
    saveSetting,
}: {
    settings: AppSettings;
    saveSetting: (key: string, value: boolean) => void;
}) {
    const { t } = useTranslation('settings');
    const [sendErrorReports, setSendErrorReports] = useState(
        settings.send_error_reports,
    );
    const [sendAnalytics, setSendAnalytics] = useState(settings.send_analytics);

    return (
        <div>
            <SectionLabel variant="section">
                {t('privacy.sectionLabel')}
            </SectionLabel>
            <div className="mt-3 flex flex-col gap-3">
                <Card className="flex items-center justify-between px-6 py-3.5">
                    <div>
                        <span className="text-sm font-medium text-ink">
                            {t('appearance.sendErrorReports.label')}
                        </span>
                        <p className="mt-0.5 text-[13px] text-ink-muted">
                            {t('privacy.description')}
                        </p>
                    </div>
                    <Toggle
                        checked={sendErrorReports}
                        onChange={() => {
                            const next = !sendErrorReports;
                            setSendErrorReports(next);
                            saveSetting('send_error_reports', next);
                        }}
                    />
                </Card>
                <Card
                    className="flex items-center justify-between px-6 py-3.5"
                    data-testid="send-analytics-setting"
                >
                    <div>
                        <span className="text-sm font-medium text-ink">
                            {t('privacy.analyticsLabel')}
                        </span>
                        <p className="mt-0.5 text-[13px] text-ink-muted">
                            {t('privacy.analyticsDescription')}
                        </p>
                    </div>
                    <Toggle
                        checked={sendAnalytics}
                        onChange={() => {
                            const next = !sendAnalytics;
                            setSendAnalytics(next);
                            setAnalyticsEnabled(next);
                            saveSetting('send_analytics', next);
                        }}
                    />
                </Card>
            </div>
        </div>
    );
}

// ─── Updates Section ─────────────────────────────────────────────────

function UpdatesSection({
    version,
    settings,
    saveSetting,
}: {
    version: string;
    settings: AppSettings;
    saveSetting: (key: string, value: boolean) => void;
}) {
    const { t } = useTranslation('settings');
    const {
        state: updateState,
        checkForUpdates,
        installUpdate,
    } = useAutoUpdater();
    const [autoUpdate, setAutoUpdate] = useState(
        (settings as Record<string, unknown>).auto_update !== false,
    );

    return (
        <div>
            <SectionLabel variant="section">
                {t('updates.sectionLabel')}
            </SectionLabel>
            <Card className="mt-3">
                {/* Version row */}
                <div className="flex items-center justify-between px-6 py-[18px]">
                    <div className="flex flex-col gap-1">
                        <span className="text-[12px] text-ink-muted">
                            {t('updates.currentVersion')}
                        </span>
                        <span className="text-[20px] font-semibold text-ink">
                            {version}
                        </span>
                    </div>
                    <div>
                        {updateState.status === 'ready' ? (
                            <Button
                                variant="primary"
                                type="button"
                                onClick={installUpdate}
                            >
                                {t('appearance.update.restart')}
                            </Button>
                        ) : (
                            <Button
                                variant="secondary"
                                type="button"
                                onClick={checkForUpdates}
                                disabled={
                                    updateState.status === 'checking' ||
                                    updateState.status === 'downloading'
                                }
                            >
                                {t('appearance.update.checkForUpdates')}
                            </Button>
                        )}
                    </div>
                </div>

                <div className="border-t border-border" />

                {/* Auto-update row */}
                <div className="flex items-center justify-between px-6 py-3.5">
                    <div>
                        <span className="text-[14px] font-medium text-ink">
                            {t('updates.autoUpdate')}
                        </span>
                        <p className="mt-0.5 text-[13px] text-ink-muted">
                            {t('updates.autoUpdateDescription')}
                        </p>
                    </div>
                    <Toggle
                        checked={autoUpdate}
                        onChange={() => {
                            const next = !autoUpdate;
                            setAutoUpdate(next);
                            saveSetting('auto_update', next);
                        }}
                    />
                </div>

                <div className="border-t border-border" />

                {/* Status row */}
                <div className="flex items-center gap-2 px-6 py-3.5">
                    {updateState.status === 'checking' && (
                        <span className="text-[12px] text-ink-muted">
                            {t('appearance.update.checking')}
                        </span>
                    )}
                    {updateState.status === 'downloading' && (
                        <span className="text-[12px] text-ink-muted">
                            {t('appearance.update.downloading', {
                                progress: updateState.progress,
                            })}
                        </span>
                    )}
                    {updateState.status === 'ready' && (
                        <span className="text-[12px] font-medium text-accent">
                            {t('appearance.update.readyToInstall', {
                                version: updateState.version,
                            })}
                        </span>
                    )}
                    {updateState.status === 'error' && (
                        <span className="text-[12px] text-delete">
                            {updateState.error}
                        </span>
                    )}
                    {(updateState.status === 'idle' ||
                        updateState.status === 'available') && (
                        <>
                            <span className="text-[13px] font-semibold text-status-final">
                                ✓
                            </span>
                            <span className="text-[12px] text-ink-muted">
                                {t('updates.upToDate')}
                            </span>
                        </>
                    )}
                </div>
            </Card>
        </div>
    );
}

// ─── Backup Section ──────────────────────────────────────────────────

type BackupPhase = 'idle' | 'exporting' | 'importing' | 'reverting' | 'done';

function BackupSection({
    backup,
}: {
    backup: { has_rollback: boolean; last_export_at: string | null };
}) {
    const { t } = useTranslation('settings');
    const fileInputRef = useRef<HTMLInputElement>(null);

    const [exportPassphrase, setExportPassphrase] = useState('');
    const [importFile, setImportFile] = useState<File | null>(null);
    const [importPassphrase, setImportPassphrase] = useState('');
    const [phase, setPhase] = useState<BackupPhase>('idle');
    const [error, setError] = useState('');
    const [doneMessage, setDoneMessage] = useState('');
    const [confirmRevertOpen, setConfirmRevertOpen] = useState(false);

    const lastExportLabel = backup.last_export_at
        ? new Date(backup.last_export_at).toLocaleString()
        : t('backup.neverExported');

    const handleExport = useCallback(
        async (e: FormEvent) => {
            e.preventDefault();
            setPhase('exporting');
            setError('');
            try {
                const formData = new FormData();
                if (exportPassphrase) {
                    formData.append('passphrase', exportPassphrase);
                }
                // No explicit Content-Type: the FormData body must set its
                // own multipart boundary, or PHP never parses the passphrase
                // and silently exports unencrypted.
                const res = await fetch(backupExport.url(), {
                    method: 'POST',
                    headers: { Accept: 'application/json' },
                    body: formData,
                });
                if (!res.ok) {
                    const json = await res.json().catch(() => ({}));
                    setError(json.message || t('backup.error.exportFailed'));
                    return;
                }
                const blob = await res.blob();
                // Symfony may emit the filename quoted or as a bare token —
                // match both, and keep an importable extension on the
                // fallback so the import picker can always select the file.
                const filename =
                    res.headers
                        .get('content-disposition')
                        ?.match(/filename="?([^";]+)"?/)?.[1] ||
                    (exportPassphrase
                        ? 'manuscript-backup.msbk'
                        : 'manuscript-backup.sqlite');
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                setExportPassphrase('');
                router.reload({ only: ['backup'] });
            } catch {
                setError(t('backup.error.exportFailed'));
            } finally {
                setPhase((p) => (p === 'exporting' ? 'idle' : p));
            }
        },
        [exportPassphrase, t],
    );

    const handleImport = useCallback(
        async (e: FormEvent) => {
            e.preventDefault();
            if (!importFile) return;
            setPhase('importing');
            setError('');
            try {
                const formData = new FormData();
                formData.append('backup', importFile);
                if (importPassphrase) {
                    formData.append('passphrase', importPassphrase);
                }
                // No explicit Content-Type — see handleExport: a forced JSON
                // content type makes PHP drop the multipart body entirely.
                const res = await fetch(backupImport.url(), {
                    method: 'POST',
                    headers: { Accept: 'application/json' },
                    body: formData,
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) {
                    setError(json.message || t('backup.error.importFailed'));
                    setPhase('idle');
                    return;
                }
                setImportFile(null);
                setImportPassphrase('');
                if (fileInputRef.current) {
                    fileInputRef.current.value = '';
                }
                setDoneMessage(json.message || t('backup.restart.import'));
                setPhase('done');
            } catch {
                setError(t('backup.error.importFailed'));
                setPhase('idle');
            }
        },
        [importFile, importPassphrase, t],
    );

    const runRevert = useCallback(async () => {
        setConfirmRevertOpen(false);
        setPhase('reverting');
        setError('');
        try {
            const res = await fetch(backupRevert.url(), {
                method: 'POST',
                headers: jsonFetchHeaders(),
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) {
                setError(json.message || t('backup.error.revertFailed'));
                setPhase('idle');
                return;
            }
            setDoneMessage(json.message || t('backup.restart.revert'));
            setPhase('done');
        } catch {
            setError(t('backup.error.revertFailed'));
            setPhase('idle');
        }
    }, [t]);

    return (
        <div>
            <SectionLabel variant="section">
                {t('backup.sectionLabel')}
            </SectionLabel>
            <Card className="mt-3">
                {phase === 'done' ? (
                    <div className="px-6 py-5">
                        <p className="text-[14px] font-medium text-ink">
                            {doneMessage}
                        </p>
                        <p className="mt-2 text-[13px] text-ink-muted">
                            {t('backup.restart.hint')}
                        </p>
                    </div>
                ) : (
                    <>
                        <form
                            onSubmit={handleExport}
                            className="flex flex-col gap-3 px-6 py-5"
                        >
                            <div>
                                <span className="text-[14px] font-medium text-ink">
                                    {t('backup.save.title')}
                                </span>
                                <p className="mt-0.5 text-[13px] text-ink-muted">
                                    {t('backup.save.description')}
                                </p>
                                <p className="mt-1 text-[12px] text-ink-muted">
                                    {t('backup.lastExport', {
                                        value: lastExportLabel,
                                    })}
                                </p>
                            </div>
                            <FormField label={t('backup.passphrase.label')}>
                                <Input
                                    type="password"
                                    value={exportPassphrase}
                                    onChange={(e) =>
                                        setExportPassphrase(e.target.value)
                                    }
                                    placeholder={t(
                                        'backup.passphrase.placeholder',
                                    )}
                                    autoComplete="new-password"
                                />
                            </FormField>
                            <p className="text-[12px] text-ink-muted">
                                {t('backup.passphrase.hint')}
                            </p>
                            <div className="flex justify-end">
                                <Button
                                    type="submit"
                                    variant="primary"
                                    disabled={phase !== 'idle'}
                                >
                                    {phase === 'exporting'
                                        ? t('backup.save.busy')
                                        : t('backup.save.button')}
                                </Button>
                            </div>
                        </form>

                        <div className="border-t border-border" />

                        <form
                            onSubmit={handleImport}
                            className="flex flex-col gap-3 px-6 py-5"
                        >
                            <div>
                                <span className="text-[14px] font-medium text-ink">
                                    {t('backup.import.title')}
                                </span>
                                <p className="mt-0.5 text-[13px] text-ink-muted">
                                    {t('backup.import.description')}
                                </p>
                            </div>
                            <FormField label={t('backup.import.fileLabel')}>
                                <div className="flex items-center gap-3">
                                    <input
                                        ref={fileInputRef}
                                        type="file"
                                        accept=".msbk,.sqlite"
                                        onChange={(e) =>
                                            setImportFile(
                                                e.target.files?.[0] ?? null,
                                            )
                                        }
                                        className="hidden"
                                    />
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={() =>
                                            fileInputRef.current?.click()
                                        }
                                    >
                                        {importFile
                                            ? t('backup.import.replaceFile')
                                            : t('backup.import.chooseFile')}
                                    </Button>
                                    <span className="truncate text-[13px] text-ink-muted">
                                        {importFile
                                            ? importFile.name
                                            : t('backup.import.noFileChosen')}
                                    </span>
                                </div>
                            </FormField>
                            <FormField
                                label={t('backup.import.passphraseLabel')}
                            >
                                <Input
                                    type="password"
                                    value={importPassphrase}
                                    onChange={(e) =>
                                        setImportPassphrase(e.target.value)
                                    }
                                    placeholder={t(
                                        'backup.import.passphrasePlaceholder',
                                    )}
                                    autoComplete="new-password"
                                />
                            </FormField>
                            <p className="text-[12px] text-ink-muted">
                                {t('backup.import.warning')}
                            </p>
                            <div className="flex justify-end">
                                <Button
                                    type="submit"
                                    variant="primary"
                                    disabled={!importFile || phase !== 'idle'}
                                    data-testid="backup-import-submit"
                                >
                                    {phase === 'importing'
                                        ? t('backup.import.busy')
                                        : t('backup.import.button')}
                                </Button>
                            </div>
                        </form>

                        {backup.has_rollback && (
                            <>
                                <div className="border-t border-border" />
                                <div className="flex flex-col gap-3 px-6 py-5">
                                    <div>
                                        <span className="text-[14px] font-medium text-ink">
                                            {t('backup.revert.title')}
                                        </span>
                                        <p className="mt-0.5 text-[13px] text-ink-muted">
                                            {t('backup.revert.description')}
                                        </p>
                                    </div>
                                    <div className="flex justify-end">
                                        <Button
                                            type="button"
                                            variant="primary"
                                            onClick={() =>
                                                setConfirmRevertOpen(true)
                                            }
                                            disabled={phase !== 'idle'}
                                        >
                                            {phase === 'reverting'
                                                ? t('backup.revert.busy')
                                                : t('backup.revert.button')}
                                        </Button>
                                    </div>
                                </div>
                            </>
                        )}

                        {error && (
                            <>
                                <div className="border-t border-border" />
                                <div className="px-6 py-3">
                                    <p className="text-[13px] text-delete">
                                        {error}
                                    </p>
                                </div>
                            </>
                        )}
                    </>
                )}
            </Card>

            {confirmRevertOpen && (
                <Dialog
                    onClose={() => setConfirmRevertOpen(false)}
                    title={t('backup.revert.title')}
                    width={440}
                >
                    <h3 className="text-[16px] font-semibold text-ink">
                        {t('backup.revert.title')}
                    </h3>
                    <p className="mt-3 text-[13px] text-ink-muted">
                        {t('backup.revertConfirm')}
                    </p>
                    <div className="mt-6 flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => setConfirmRevertOpen(false)}
                        >
                            {t('back')}
                        </Button>
                        <Button
                            type="button"
                            variant="primary"
                            onClick={runRevert}
                        >
                            {t('backup.revert.button')}
                        </Button>
                    </div>
                </Dialog>
            )}
        </div>
    );
}

// ─── Sidebar ─────────────────────────────────────────────────────────

type SectionKey =
    | 'license'
    | 'language'
    | 'appearance'
    | 'toolbar'
    | 'ai-features'
    | 'privacy'
    | 'updates'
    | 'backup';

type NavSection = { key: SectionKey; label: string; groupKey?: string };

const NAV_ITEMS: NavSection[] = [
    { key: 'license', label: 'section.license' },
    {
        key: 'language',
        label: 'language.navLabel',
        groupKey: 'sidebar.general',
    },
    {
        key: 'appearance',
        label: 'section.appearance',
        groupKey: 'sidebar.general',
    },
    { key: 'toolbar', label: 'sidebar.toolbar', groupKey: 'sidebar.editor' },
    {
        key: 'ai-features',
        label: 'sidebar.aiFeatures',
        groupKey: 'sidebar.editor',
    },
    { key: 'privacy', label: 'privacy.navLabel', groupKey: 'sidebar.account' },
    { key: 'updates', label: 'updates.navLabel', groupKey: 'sidebar.account' },
    { key: 'backup', label: 'backup.navLabel', groupKey: 'sidebar.account' },
];

function SettingsSidebar({
    activeSection,
    onNavigate,
}: {
    activeSection: SectionKey;
    onNavigate: (key: SectionKey) => void;
}) {
    const { t } = useTranslation('settings');
    const backHref =
        new URLSearchParams(window.location.search).get('from') ||
        booksIndex.url();

    return (
        <aside className="flex h-full w-60 shrink-0 flex-col border-r border-border bg-surface-card">
            <div className="px-5 py-4">
                <Link
                    href={backHref}
                    className="flex items-center gap-1.5 text-[12px] font-medium text-ink-muted transition-colors hover:text-ink"
                >
                    <svg
                        width="12"
                        height="12"
                        viewBox="0 0 16 16"
                        fill="none"
                        className="shrink-0"
                    >
                        <path
                            d="M10 3L5 8l5 5"
                            stroke="currentColor"
                            strokeWidth="1.5"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        />
                    </svg>
                    {t('back')}
                </Link>
            </div>
            <nav className="flex flex-1 flex-col gap-0.5 px-2.5">
                {NAV_ITEMS.map((item, index) => {
                    const prevGroupKey =
                        index > 0 ? NAV_ITEMS[index - 1].groupKey : undefined;
                    const showGroup =
                        item.groupKey && item.groupKey !== prevGroupKey;

                    return (
                        <div key={item.key}>
                            {showGroup && (
                                <SectionLabel
                                    variant="section"
                                    className="mt-3 mb-1.5 block px-2.5"
                                >
                                    {t(item.groupKey!)}
                                </SectionLabel>
                            )}
                            <NavItem
                                label={t(item.label)}
                                isActive={activeSection === item.key}
                                activeVariant="inverted"
                                onClick={() => onNavigate(item.key)}
                            />
                        </div>
                    );
                })}
            </nav>
        </aside>
    );
}

// ─── Main Page ───────────────────────────────────────────────────────

export default function Settings({
    settings,
    ai_providers,
    version,
    backup,
}: Props) {
    const { t } = useTranslation('settings');

    const licenseRef = useRef<HTMLDivElement>(null);
    const languageRef = useRef<HTMLDivElement>(null);
    const appearanceRef = useRef<HTMLDivElement>(null);
    const toolbarRef = useRef<HTMLDivElement>(null);
    const aiFeaturesRef = useRef<HTMLDivElement>(null);
    const privacyRef = useRef<HTMLDivElement>(null);
    const updatesRef = useRef<HTMLDivElement>(null);
    const backupRef = useRef<HTMLDivElement>(null);

    const sectionRefs = useRef<
        Record<SectionKey, React.RefObject<HTMLDivElement | null>>
    >({
        license: licenseRef,
        language: languageRef,
        appearance: appearanceRef,
        toolbar: toolbarRef,
        'ai-features': aiFeaturesRef,
        privacy: privacyRef,
        updates: updatesRef,
        backup: backupRef,
    });

    const [activeSection, setActiveSection] = useState<SectionKey>(() => {
        const params = new URLSearchParams(window.location.search);
        const section = params.get('section') as SectionKey | null;
        return section && NAV_ITEMS.some((item) => item.key === section)
            ? section
            : 'license';
    });
    const mainRef = useRef<HTMLDivElement>(null);

    // Track active section via IntersectionObserver
    useEffect(() => {
        const main = mainRef.current;
        if (!main) return;

        const observer = new IntersectionObserver(
            (entries) => {
                // Pick the topmost intersecting entry to avoid nondeterminism
                let topEntry: IntersectionObserverEntry | null = null;
                for (const entry of entries) {
                    if (entry.isIntersecting) {
                        if (
                            !topEntry ||
                            entry.boundingClientRect.top <
                                topEntry.boundingClientRect.top
                        ) {
                            topEntry = entry;
                        }
                    }
                }
                if (topEntry) {
                    const key = topEntry.target.getAttribute(
                        'data-section',
                    ) as SectionKey | null;
                    if (key) setActiveSection(key);
                }
            },
            { root: main, rootMargin: '-20% 0px -70% 0px', threshold: 0 },
        );

        const refs = sectionRefs.current;
        Object.entries(refs).forEach(([key, ref]) => {
            if (ref.current) {
                ref.current.setAttribute('data-section', key);
                observer.observe(ref.current);
            }
        });

        return () => observer.disconnect();
    }, []);

    // Scroll to section from ?section= query param on mount
    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const section = params.get('section') as SectionKey | null;
        if (section && sectionRefs.current[section]?.current) {
            // Small delay so the DOM is fully laid out
            requestAnimationFrame(() => {
                sectionRefs.current[section]?.current?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                });
            });
        }
    }, []);

    const handleNavigate = useCallback((key: SectionKey) => {
        sectionRefs.current[key]?.current?.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
    }, []);

    const saveSetting = useCallback(
        (key: string, value: boolean | string | number) => {
            fetch(update.url(), {
                method: 'PUT',
                headers: jsonFetchHeaders(),
                body: JSON.stringify({ key, value }),
            }).then(() => {
                router.reload({ only: ['settings'] });
            });
        },
        [],
    );

    return (
        <>
            <Head title={t('title')} />
            <div className="flex h-screen flex-col overflow-hidden bg-surface">
                <div className="flex min-h-0 flex-1">
                    <SettingsSidebar
                        activeSection={activeSection}
                        onNavigate={handleNavigate}
                    />
                    <main ref={mainRef} className="flex-1 overflow-y-auto">
                        <div className="mx-auto w-full max-w-[760px] px-12 pt-12 pb-[80vh]">
                            <div className="flex flex-col gap-9">
                                {/* Page header */}
                                <PageHeader
                                    title={t('title')}
                                    subtitle={t('pageDescription')}
                                />

                                <div ref={licenseRef} data-section="license">
                                    <LicenseSection />
                                </div>

                                <div className="flex flex-col gap-9">
                                    <div
                                        ref={languageRef}
                                        data-section="language"
                                    >
                                        <LanguageSection />
                                    </div>
                                    <div
                                        ref={appearanceRef}
                                        data-section="appearance"
                                    >
                                        <AppearanceSection />
                                    </div>
                                    <div
                                        ref={toolbarRef}
                                        data-section="toolbar"
                                    >
                                        <EditorSection
                                            settings={settings}
                                            saveSetting={saveSetting}
                                        />
                                    </div>
                                </div>

                                <div className="flex flex-col gap-9">
                                    <div
                                        ref={aiFeaturesRef}
                                        data-section="ai-features"
                                    >
                                        <AiProvidersSection
                                            providers={ai_providers}
                                        />
                                    </div>
                                    <div data-section="speech-input">
                                        <SpeechInputSection />
                                    </div>
                                </div>

                                <div className="flex flex-col gap-9">
                                    <div
                                        ref={privacyRef}
                                        data-section="privacy"
                                    >
                                        <PrivacySection
                                            settings={settings}
                                            saveSetting={saveSetting}
                                        />
                                    </div>
                                    <div
                                        ref={updatesRef}
                                        data-section="updates"
                                    >
                                        <UpdatesSection
                                            version={version}
                                            settings={settings}
                                            saveSetting={saveSetting}
                                        />
                                    </div>
                                    <div ref={backupRef} data-section="backup">
                                        <BackupSection backup={backup} />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </main>
                </div>
            </div>
        </>
    );
}
