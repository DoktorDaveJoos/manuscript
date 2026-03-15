import { useTranslation } from 'react-i18next';
import { update } from '@/actions/App/Http/Controllers/AppSettingsController';
import { useAutoUpdater } from '@/hooks/useAutoUpdater';
import { useTheme } from '@/hooks/useTheme';
import SettingsLayout from '@/layouts/SettingsLayout';
import { jsonFetchHeaders } from '@/lib/utils';
import type { Theme } from '@/lib/theme';
import type { AppSettings } from '@/types/models';
import { router } from '@inertiajs/react';
import { useState, useCallback } from 'react';

interface Props {
    settings: AppSettings;
    book?: { id: number; title: string } | null;
    version: string;
}

const THEME_OPTIONS = [
    { value: 'light' as Theme, labelKey: 'appearance.theme.light' as const, descriptionKey: 'appearance.theme.lightDescription' as const },
    { value: 'dark' as Theme, labelKey: 'appearance.theme.dark' as const, descriptionKey: 'appearance.theme.darkDescription' as const },
    { value: 'system' as Theme, labelKey: 'appearance.theme.system' as const, descriptionKey: 'appearance.theme.systemDescription' as const },
];

function Toggle({ checked, onChange }: { checked: boolean; onChange: () => void }) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            onClick={onChange}
            className={`relative inline-flex h-[22px] w-[40px] shrink-0 items-center rounded-full transition-colors ${
                checked ? 'bg-accent' : 'bg-status-draft'
            }`}
        >
            <span
                className={`inline-block h-[18px] w-[18px] rounded-full bg-white shadow-sm transition-transform ${
                    checked ? 'translate-x-[20px]' : 'translate-x-[2px]'
                }`}
            />
        </button>
    );
}

function SettingRow({
    label,
    description,
    checked,
    onChange,
    border = true,
}: {
    label: string;
    description: string;
    checked: boolean;
    onChange: () => void;
    border?: boolean;
}) {
    return (
        <div className={`flex items-center justify-between py-3.5 ${border ? 'border-b border-border-light' : ''}`}>
            <div>
                <span className="text-[14px] font-medium text-ink">{label}</span>
                <p className="mt-0.5 text-[13px] text-ink-muted">{description}</p>
            </div>
            <Toggle checked={checked} onChange={onChange} />
        </div>
    );
}

export default function Appearance({ settings, book, version }: Props) {
    const { t } = useTranslation('settings');
    const { theme, setTheme } = useTheme();
    const { state: updateState, checkForUpdates, installUpdate } = useAutoUpdater();
    const [showAi, setShowAi] = useState(settings.show_ai_features);
    const [hideToolbar, setHideToolbar] = useState(settings.hide_formatting_toolbar);
    const [sendErrorReports, setSendErrorReports] = useState(settings.send_error_reports);

    const saveSetting = useCallback((key: string, value: boolean) => {
        fetch(update.url(), {
            method: 'PUT',
            headers: jsonFetchHeaders(),
            body: JSON.stringify({ key, value }),
        }).then(() => {
            router.reload({ only: ['app_settings'] });
        });
    }, []);

    return (
        <SettingsLayout activeSection="appearance" book={book} title={t('appearance.title')}>
            <div className="flex flex-col gap-6">
                <div>
                    <h1 className="text-[22px] font-semibold tracking-[-0.01em] text-ink">{t('appearance.title')}</h1>
                    <p className="mt-1 text-[14px] text-ink-muted">
                        {t('appearance.description')}
                    </p>
                </div>

                {/* Theme */}
                <div className="rounded-lg border border-border bg-surface-card p-6">
                    <div className="flex flex-col gap-4">
                        <div>
                            <span className="text-[15px] font-medium text-ink">{t('appearance.theme.title')}</span>
                            <p className="mt-0.5 text-[13px] text-ink-muted">{t('appearance.theme.description')}</p>
                        </div>
                        <div className="flex gap-3">
                            {THEME_OPTIONS.map((option) => (
                                <button
                                    key={option.value}
                                    type="button"
                                    onClick={() => setTheme(option.value)}
                                    className={`flex flex-1 flex-col rounded-lg border px-4 py-3 text-left transition-colors ${
                                        theme === option.value
                                            ? 'border-accent bg-accent/10 text-ink'
                                            : 'border-border text-ink-muted hover:border-border-dashed hover:text-ink'
                                    }`}
                                >
                                    <span className="text-[14px] font-medium">{t(option.labelKey)}</span>
                                    <span className="mt-0.5 text-[12px] text-ink-muted">{t(option.descriptionKey)}</span>
                                </button>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Show AI Features */}
                <div className="rounded-lg border border-border bg-surface-card px-6">
                    <SettingRow
                        label={t('appearance.showAi.label')}
                        description={t('appearance.showAi.description')}
                        checked={showAi}
                        onChange={() => {
                            const next = !showAi;
                            setShowAi(next);
                            saveSetting('show_ai_features', next);
                        }}
                        border={false}
                    />
                </div>

                {/* Editor section */}
                <div>
                    <span className="text-[11px] font-medium uppercase tracking-[0.08em] text-ink-faint">
                        {t('appearance.editor')}
                    </span>
                    <div className="mt-3 rounded-lg border border-border bg-surface-card px-6">
                        <SettingRow
                            label={t('appearance.hideToolbar.label')}
                            description={t('appearance.hideToolbar.description')}
                            checked={hideToolbar}
                            onChange={() => {
                                const next = !hideToolbar;
                                setHideToolbar(next);
                                saveSetting('hide_formatting_toolbar', next);
                            }}
                            border={false}
                        />
                    </div>
                </div>

                {/* Privacy */}
                <div>
                    <span className="text-[11px] font-medium uppercase tracking-[0.08em] text-ink-faint">
                        {t('appearance.privacy')}
                    </span>
                    <div className="mt-3 rounded-lg border border-border bg-surface-card px-6">
                        <SettingRow
                            label={t('appearance.sendErrorReports.label')}
                            description={t('appearance.sendErrorReports.description')}
                            checked={sendErrorReports}
                            onChange={() => {
                                const next = !sendErrorReports;
                                setSendErrorReports(next);
                                saveSetting('send_error_reports', next);
                            }}
                            border={false}
                        />
                    </div>
                </div>

                {/* Version info */}
                <div className="rounded-lg border border-border bg-surface-card p-6">
                    <div className="flex items-start justify-between">
                        <div className="flex flex-col gap-2">
                            <span className="text-[13px] font-medium text-ink-muted">{t('appearance.version')}</span>
                            <p className="text-[15px] text-ink">{version}</p>
                            {updateState.status === 'checking' && (
                                <p className="text-[13px] text-ink-muted">{t('appearance.update.checking')}</p>
                            )}
                            {updateState.status === 'downloading' && (
                                <p className="text-[13px] text-ink-muted">
                                    {t('appearance.update.downloading', { progress: updateState.progress })}
                                </p>
                            )}
                            {updateState.status === 'ready' && (
                                <p className="text-[13px] font-medium text-accent">
                                    {t('appearance.update.readyToInstall', { version: updateState.version })}
                                </p>
                            )}
                            {updateState.status === 'error' && (
                                <p className="text-[13px] text-red-500">{updateState.error}</p>
                            )}
                        </div>
                        <div>
                            {updateState.status === 'ready' ? (
                                <button
                                    type="button"
                                    onClick={installUpdate}
                                    className="rounded-md bg-accent px-3.5 py-1.5 text-[13px] font-medium text-white transition-colors hover:bg-accent/90"
                                >
                                    {t('appearance.update.restart')}
                                </button>
                            ) : (
                                <button
                                    type="button"
                                    onClick={checkForUpdates}
                                    disabled={updateState.status === 'checking' || updateState.status === 'downloading'}
                                    className="rounded-md border border-border px-3.5 py-1.5 text-[13px] font-medium text-ink transition-colors hover:bg-surface disabled:opacity-50"
                                >
                                    {t('appearance.update.checkForUpdates')}
                                </button>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    );
}
