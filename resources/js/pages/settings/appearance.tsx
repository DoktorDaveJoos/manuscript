import { router } from '@inertiajs/react';
import { useState, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { update } from '@/actions/App/Http/Controllers/AppSettingsController';
import Button from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import Toggle from '@/components/ui/Toggle';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/ToggleGroup';
import { useAutoUpdater } from '@/hooks/useAutoUpdater';
import { useTheme } from '@/hooks/useTheme';
import SettingsLayout from '@/layouts/SettingsLayout';
import type { Theme } from '@/lib/theme';
import { jsonFetchHeaders } from '@/lib/utils';
import type { AppSettings } from '@/types/models';

interface Props {
    settings: AppSettings;
    book?: { id: number; title: string } | null;
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
        <div
            className={`flex items-center justify-between py-3.5 ${border ? 'border-b border-border-light' : ''}`}
        >
            <div>
                <span className="text-[14px] font-medium text-ink">
                    {label}
                </span>
                <p className="mt-0.5 text-[13px] text-ink-muted">
                    {description}
                </p>
            </div>
            <Toggle checked={checked} onChange={onChange} />
        </div>
    );
}

export default function Appearance({ settings, book, version }: Props) {
    const { t } = useTranslation('settings');
    const { theme, setTheme } = useTheme();
    const {
        state: updateState,
        checkForUpdates,
        installUpdate,
    } = useAutoUpdater();
    const [showAi, setShowAi] = useState(settings.show_ai_features);
    const [hideToolbar, setHideToolbar] = useState(
        settings.hide_formatting_toolbar,
    );
    const [sendErrorReports, setSendErrorReports] = useState(
        settings.send_error_reports,
    );

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
        <SettingsLayout
            activeSection="appearance"
            book={book}
            title={t('appearance.title')}
        >
            <div className="flex flex-col gap-6">
                <div>
                    <h1 className="text-xl font-semibold tracking-[-0.01em] text-ink">
                        {t('appearance.title')}
                    </h1>
                    <p className="mt-1 text-[14px] text-ink-muted">
                        {t('appearance.description')}
                    </p>
                </div>

                {/* Theme */}
                <Card className="p-6">
                    <div className="flex flex-col gap-4">
                        <div>
                            <span className="text-sm font-medium text-ink">
                                {t('appearance.theme.title')}
                            </span>
                            <p className="mt-0.5 text-[13px] text-ink-muted">
                                {t('appearance.theme.description')}
                            </p>
                        </div>
                        <ToggleGroup
                            type="single"
                            value={theme}
                            onValueChange={(val) => {
                                if (val) setTheme(val as typeof theme);
                            }}
                        >
                            {THEME_OPTIONS.map((option) => (
                                <ToggleGroupItem
                                    key={option.value}
                                    value={option.value}
                                    className="flex flex-1 flex-col items-start rounded-lg px-4 py-3 text-left"
                                >
                                    <span className="text-[14px] font-medium">
                                        {t(option.labelKey)}
                                    </span>
                                    <span className="mt-0.5 text-[12px] opacity-70">
                                        {t(option.descriptionKey)}
                                    </span>
                                </ToggleGroupItem>
                            ))}
                        </ToggleGroup>
                    </div>
                </Card>

                {/* Show AI Features */}
                <Card className="px-6">
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
                </Card>

                {/* Editor section */}
                <div>
                    <span className="text-[11px] font-medium tracking-[0.08em] text-ink-faint uppercase">
                        {t('appearance.editor')}
                    </span>
                    <Card className="mt-3 px-6">
                        <SettingRow
                            label={t('appearance.hideToolbar.label')}
                            description={t(
                                'appearance.hideToolbar.description',
                            )}
                            checked={hideToolbar}
                            onChange={() => {
                                const next = !hideToolbar;
                                setHideToolbar(next);
                                saveSetting('hide_formatting_toolbar', next);
                            }}
                            border={false}
                        />
                    </Card>
                </div>

                {/* Privacy */}
                <div>
                    <span className="text-[11px] font-medium tracking-[0.08em] text-ink-faint uppercase">
                        {t('appearance.privacy')}
                    </span>
                    <Card className="mt-3 px-6">
                        <SettingRow
                            label={t('appearance.sendErrorReports.label')}
                            description={t(
                                'appearance.sendErrorReports.description',
                            )}
                            checked={sendErrorReports}
                            onChange={() => {
                                const next = !sendErrorReports;
                                setSendErrorReports(next);
                                saveSetting('send_error_reports', next);
                            }}
                            border={false}
                        />
                    </Card>
                </div>

                {/* Version info */}
                <Card className="p-6">
                    <div className="flex items-start justify-between">
                        <div className="flex flex-col gap-2">
                            <span className="text-[13px] font-medium text-ink-muted">
                                {t('appearance.version')}
                            </span>
                            <p className="text-sm text-ink">{version}</p>
                            {updateState.status === 'checking' && (
                                <p className="text-[13px] text-ink-muted">
                                    {t('appearance.update.checking')}
                                </p>
                            )}
                            {updateState.status === 'downloading' && (
                                <p className="text-[13px] text-ink-muted">
                                    {t('appearance.update.downloading', {
                                        progress: updateState.progress,
                                    })}
                                </p>
                            )}
                            {updateState.status === 'ready' && (
                                <p className="text-[13px] font-medium text-accent">
                                    {t('appearance.update.readyToInstall', {
                                        version: updateState.version,
                                    })}
                                </p>
                            )}
                            {updateState.status === 'error' && (
                                <p className="text-[13px] text-delete">
                                    {updateState.error}
                                </p>
                            )}
                        </div>
                        <div>
                            {updateState.status === 'ready' ? (
                                <Button
                                    variant="accent"
                                    size="sm"
                                    type="button"
                                    onClick={installUpdate}
                                >
                                    {t('appearance.update.restart')}
                                </Button>
                            ) : (
                                <Button
                                    variant="secondary"
                                    size="sm"
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
                </Card>
            </div>
        </SettingsLayout>
    );
}
