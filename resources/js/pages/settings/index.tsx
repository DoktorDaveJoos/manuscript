import { Head, Link, router, usePage } from '@inertiajs/react';
import { Lock, Trash2 } from 'lucide-react';
import { useState, useCallback, useRef, useEffect } from 'react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import {
    update as updateAiProvider,
    deleteKey,
    test as testConnection,
} from '@/actions/App/Http/Controllers/AiSettingsController';
import { update } from '@/actions/App/Http/Controllers/AppSettingsController';
import { index as booksIndex } from '@/actions/App/Http/Controllers/BookController';
import {
    activate,
    deactivate,
    revalidate,
} from '@/actions/App/Http/Controllers/LicenseController';
import {
    updateWritingStyle,
    updateCopyright,
    updateAcknowledgment,
    updateAboutAuthor,
    updateProsePassRules,
} from '@/actions/App/Http/Controllers/SettingsController';
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
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
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
import type { Theme } from '@/lib/theme';
import { cn, jsonFetchHeaders, saveAppSetting } from '@/lib/utils';
import type { AppSettings } from '@/types/models';
import type {
    AppSettings,
    AiSetting,
    License,
    ProsePassRule,
} from '@/types/models';

type ProviderSetting = AiSetting & {
    label: string;
    supports_embeddings: boolean;
};

interface Props {
    settings: AppSettings;
    ai_providers: ProviderSetting[];
    writing_style_text: string;
    copyright_text: string;
    acknowledgment_text: string;
    about_author_text: string;
    prose_pass_rules: ProsePassRule[];
    version: string;
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
                                        variant="accent"
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
        i18n.changeLanguage(locale);
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
    const configured = setting.requires_api_key
        ? setting.has_api_key
        : !!setting.base_url;

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
                setTestStatus(
                    json.success
                        ? { type: 'success', message: json.message }
                        : { type: 'error', message: json.message },
                );
                setTimeout(() => setTestStatus({ type: 'idle' }), 5000);
            })
            .catch(() => {
                setTestStatus({
                    type: 'error',
                    message: t('aiProviders.testFailed'),
                });
                setTimeout(() => setTestStatus({ type: 'idle' }), 5000);
            });
    }, [setting.provider, t]);

    return (
        <form onSubmit={handleSave} className="pb-1">
            <div className="flex flex-col gap-5 pl-[30px]">
                {setting.requires_api_key && (
                    <FormField label={t('aiProviders.apiKey')}>
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
                )}

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
                        disabled={testStatus.type === 'loading' || !configured}
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
                    {testStatus.type === 'error' && (
                        <span className="text-[12px] font-medium text-danger">
                            {testStatus.message}
                        </span>
                    )}
                </div>
            </div>
        </form>
    );
}

function AiProvidersSection({ providers }: { providers: ProviderSetting[] }) {
    const { t } = useTranslation('settings');
    const { license } = usePage<{ license: License }>().props;
    const locked = !license.active;

    const enabledProvider = providers.find((p) => p.enabled)?.provider ?? null;

    const handleSelect = useCallback(
        (provider: string) => {
            if (locked) return;
            fetch(updateAiProvider.url(provider), {
                method: 'PUT',
                headers: jsonFetchHeaders(),
                body: JSON.stringify({ enabled: true }),
            }).then(() => {
                router.reload({ only: ['ai_providers'] });
            });
        },
        [locked],
    );

    const handleAccordionChange = useCallback(
        (value: string) => {
            if (locked) return;
            if (value && value !== enabledProvider) {
                handleSelect(value);
            }
        },
        [locked, enabledProvider, handleSelect],
    );

    return (
        <div>
            <SectionLabel variant="section">
                {t('aiProviders.title')}
            </SectionLabel>
            <Card className={cn('mt-3 px-5', locked && 'opacity-50')}>
                <Accordion
                    type="single"
                    collapsible
                    defaultValue={enabledProvider ?? undefined}
                    onValueChange={handleAccordionChange}
                    disabled={locked}
                >
                    {providers.map((setting) => {
                        const isSelected = setting.enabled;
                        const configured = setting.requires_api_key
                            ? setting.has_api_key
                            : !!setting.base_url;

                        return (
                            <AccordionItem
                                key={setting.provider}
                                value={setting.provider}
                            >
                                <AccordionTrigger className="px-0 py-4">
                                    <div className="flex flex-1 items-center gap-3">
                                        {locked ? (
                                            <Lock
                                                size={14}
                                                className="text-ink-faint"
                                            />
                                        ) : (
                                            <span
                                                className={`flex size-[18px] items-center justify-center rounded-full border-2 transition-colors ${isSelected ? 'border-ink' : 'border-border'}`}
                                            >
                                                {isSelected && (
                                                    <span className="size-[10px] rounded-full bg-ink" />
                                                )}
                                            </span>
                                        )}
                                        <span
                                            className={`text-sm ${isSelected ? 'font-medium' : ''} text-ink`}
                                        >
                                            {setting.label}
                                        </span>
                                        <span className="flex-1" />
                                        {!locked && (
                                            <Badge
                                                className="mr-2"
                                                variant={
                                                    configured
                                                        ? 'success'
                                                        : 'secondary'
                                                }
                                            >
                                                {configured
                                                    ? t(
                                                          'aiProviders.configured',
                                                      )
                                                    : t(
                                                          'aiProviders.notConfigured',
                                                      )}
                                            </Badge>
                                        )}
                                    </div>
                                </AccordionTrigger>
                                <AccordionContent>
                                    {!locked && (
                                        <ProviderForm setting={setting} />
                                    )}
                                </AccordionContent>
                            </AccordionItem>
                        );
                    })}
                </Accordion>
            </Card>
        </div>
    );
}

// ─── Markdown Textarea Section (shared) ─────────────────────────────

function MarkdownTextareaSection({
    initialText,
    saveUrl,
    fieldName,
    i18nPrefix,
    sectionLabelKey,
}: {
    initialText: string;
    saveUrl: string;
    fieldName: string;
    i18nPrefix: string;
    sectionLabelKey?: string;
}) {
    const { t } = useTranslation('settings');
    const [text, setText] = useState(initialText);
    const [saving, setSaving] = useState(false);
    const [saved, setSaved] = useState(false);
    const lastSavedRef = useRef(initialText);

    const handleSave = useCallback(() => {
        if (!text || text === lastSavedRef.current) return;
        setSaving(true);
        setSaved(false);

        fetch(saveUrl, {
            method: 'PUT',
            headers: jsonFetchHeaders(),
            body: JSON.stringify({ [fieldName]: text }),
        })
            .then(async (res) => {
                if (!res.ok) throw new Error('Save failed');
                lastSavedRef.current = text;
                setSaved(true);
                setTimeout(() => setSaved(false), 3000);
            })
            .catch(() => {})
            .finally(() => setSaving(false));
    }, [text, saveUrl, fieldName]);

    return (
        <div>
            <SectionLabel variant="section">
                {t(sectionLabelKey ?? `${i18nPrefix}.title`)}
            </SectionLabel>
            <Card className="mt-3 p-6">
                <div className="flex flex-col gap-4">
                    <div>
                        <span className="text-sm font-medium text-ink">
                            {t(`${i18nPrefix}.title`)}
                        </span>
                        <p className="mt-1 text-[13px] text-ink-muted">
                            {t(`${i18nPrefix}.description`)}
                        </p>
                    </div>
                    <div>
                        <div className="flex items-center justify-between rounded-t-md border border-border bg-surface px-3 py-2">
                            <div className="flex items-center gap-1.5">
                                <svg
                                    width="14"
                                    height="14"
                                    viewBox="0 0 16 16"
                                    fill="none"
                                    className="text-ink-faint"
                                >
                                    <path
                                        d="M2 4h12M4 8h8M6 12h4"
                                        stroke="currentColor"
                                        strokeWidth="1.5"
                                        strokeLinecap="round"
                                    />
                                </svg>
                                <span className="text-[12px] text-ink-faint">
                                    {t(`${i18nPrefix}.markdown`)}
                                </span>
                            </div>
                            {(saving || saved) && (
                                <span className="text-[12px] font-medium text-status-final">
                                    {saving
                                        ? t(`${i18nPrefix}.saving`)
                                        : t(`${i18nPrefix}.saved`)}
                                </span>
                            )}
                        </div>
                        <textarea
                            value={text}
                            onChange={(e) => setText(e.target.value)}
                            onBlur={handleSave}
                            placeholder={t(`${i18nPrefix}.placeholder`)}
                            className="h-[200px] w-full resize-y rounded-b-md border border-t-0 border-border bg-surface-card px-3 py-2.5 font-mono text-[13px] leading-[1.7] text-ink placeholder:text-ink-faint focus:border-ink focus:outline-none"
                        />
                    </div>
                </div>
            </Card>
        </div>
    );
}

// ─── Revision Rules Section ──────────────────────────────────────────

function RevisionRulesSection({
    initialRules,
}: {
    initialRules: ProsePassRule[];
}) {
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
                headers: jsonFetchHeaders(),
                body: JSON.stringify({ rules: updated }),
            }).catch(() => {
                setRules(rules);
            });
        },
        [rules],
    );

    return (
        <div>
            <SectionLabel variant="section">
                {t('prosePassRules.title')}
            </SectionLabel>
            <Card className="mt-3">
                <div className="px-6 pt-5 pb-4">
                    <span className="text-sm font-medium text-ink">
                        {t('prosePassRules.title')}
                    </span>
                    <p className="mt-1 text-[13px] text-ink-muted">
                        {t('prosePassRules.description')}
                    </p>
                </div>
                <div className="border-t border-border" />
                {rules.map((rule, i) => (
                    <div key={rule.key}>
                        <div className="flex items-center justify-between px-6 py-3.5">
                            <div>
                                <span className="text-[14px] font-medium text-ink">
                                    {rule.label}
                                </span>
                                <p className="mt-0.5 text-[13px] text-ink-muted">
                                    {rule.description}
                                </p>
                            </div>
                            <Toggle
                                checked={rule.enabled}
                                onChange={() => toggleRule(rule.key)}
                            />
                        </div>
                        {i < rules.length - 1 && (
                            <div className="border-t border-border" />
                        )}
                    </div>
                ))}
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

    return (
        <div>
            <SectionLabel variant="section">
                {t('privacy.sectionLabel')}
            </SectionLabel>
            <Card className="mt-3 flex items-center justify-between px-6 py-3.5">
                <div>
                    <span className="text-[14px] font-medium text-ink">
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
                                variant="accent"
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

// ─── Sidebar ─────────────────────────────────────────────────────────

type SectionKey =
    | 'license'
    | 'language'
    | 'appearance'
    | 'toolbar'
    | 'ai-features'
    | 'writing-style'
    | 'revision-rules'
    | 'copyright'
    | 'acknowledgment'
    | 'about-author'
    | 'privacy'
    | 'updates';

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
    {
        key: 'writing-style',
        label: 'section.writingStyle',
        groupKey: 'sidebar.editor',
    },
    {
        key: 'revision-rules',
        label: 'section.prosePassRules',
        groupKey: 'sidebar.editor',
    },
    {
        key: 'copyright',
        label: 'copyright.navLabel',
        groupKey: 'sidebar.print',
    },
    {
        key: 'acknowledgment',
        label: 'acknowledgment.navLabel',
        groupKey: 'sidebar.print',
    },
    {
        key: 'about-author',
        label: 'aboutAuthor.navLabel',
        groupKey: 'sidebar.print',
    },
    { key: 'privacy', label: 'privacy.navLabel', groupKey: 'sidebar.account' },
    { key: 'updates', label: 'updates.navLabel', groupKey: 'sidebar.account' },
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
    writing_style_text,
    copyright_text,
    acknowledgment_text,
    about_author_text,
    prose_pass_rules,
    version,
}: Props) {
    const { t } = useTranslation('settings');

    const licenseRef = useRef<HTMLDivElement>(null);
    const languageRef = useRef<HTMLDivElement>(null);
    const appearanceRef = useRef<HTMLDivElement>(null);
    const toolbarRef = useRef<HTMLDivElement>(null);
    const aiFeaturesRef = useRef<HTMLDivElement>(null);
    const writingStyleRef = useRef<HTMLDivElement>(null);
    const revisionRulesRef = useRef<HTMLDivElement>(null);
    const copyrightRef = useRef<HTMLDivElement>(null);
    const acknowledgmentRef = useRef<HTMLDivElement>(null);
    const aboutAuthorRef = useRef<HTMLDivElement>(null);
    const privacyRef = useRef<HTMLDivElement>(null);
    const updatesRef = useRef<HTMLDivElement>(null);

    const sectionRefs = useRef<
        Record<SectionKey, React.RefObject<HTMLDivElement | null>>
    >({
        license: licenseRef,
        language: languageRef,
        appearance: appearanceRef,
        toolbar: toolbarRef,
        'ai-features': aiFeaturesRef,
        'writing-style': writingStyleRef,
        'revision-rules': revisionRulesRef,
        copyright: copyrightRef,
        acknowledgment: acknowledgmentRef,
        'about-author': aboutAuthorRef,
        privacy: privacyRef,
        updates: updatesRef,
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
                                    <div
                                        ref={writingStyleRef}
                                        data-section="writing-style"
                                    >
                                        <MarkdownTextareaSection
                                            initialText={writing_style_text}
                                            saveUrl={updateWritingStyle.url()}
                                            fieldName="writing_style_text"
                                            i18nPrefix="writingStyle"
                                        />
                                    </div>
                                    <div
                                        ref={revisionRulesRef}
                                        data-section="revision-rules"
                                    >
                                        <RevisionRulesSection
                                            initialRules={prose_pass_rules}
                                        />
                                    </div>
                                </div>

                                <div className="flex flex-col gap-9">
                                    <div
                                        ref={copyrightRef}
                                        data-section="copyright"
                                    >
                                        <MarkdownTextareaSection
                                            initialText={copyright_text}
                                            saveUrl={updateCopyright.url()}
                                            fieldName="copyright_text"
                                            i18nPrefix="copyright"
                                            sectionLabelKey="copyright.sectionLabel"
                                        />
                                    </div>
                                    <div
                                        ref={acknowledgmentRef}
                                        data-section="acknowledgment"
                                    >
                                        <MarkdownTextareaSection
                                            initialText={acknowledgment_text}
                                            saveUrl={updateAcknowledgment.url()}
                                            fieldName="acknowledgment_text"
                                            i18nPrefix="acknowledgment"
                                            sectionLabelKey="acknowledgment.sectionLabel"
                                        />
                                    </div>
                                    <div
                                        ref={aboutAuthorRef}
                                        data-section="about-author"
                                    >
                                        <MarkdownTextareaSection
                                            initialText={about_author_text}
                                            saveUrl={updateAboutAuthor.url()}
                                            fieldName="about_author_text"
                                            i18nPrefix="aboutAuthor"
                                            sectionLabelKey="aboutAuthor.sectionLabel"
                                        />
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
                                </div>
                            </div>
                        </div>
                    </main>
                </div>
            </div>
        </>
    );
}
