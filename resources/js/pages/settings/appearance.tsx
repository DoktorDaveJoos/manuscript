import { update } from '@/actions/App/Http/Controllers/AppSettingsController';
import { useTheme } from '@/hooks/useTheme';
import SettingsLayout from '@/layouts/SettingsLayout';
import { getXsrfToken } from '@/lib/csrf';
import type { Theme } from '@/lib/theme';
import type { AppSettings } from '@/types/models';
import { router } from '@inertiajs/react';
import { useState, useCallback } from 'react';

interface Props {
    settings: AppSettings;
    book?: { id: number; title: string } | null;
    version: string;
}

const THEME_OPTIONS: { value: Theme; label: string; description: string }[] = [
    { value: 'light', label: 'Light', description: 'Always use light mode' },
    { value: 'dark', label: 'Dark', description: 'Always use dark mode' },
    { value: 'system', label: 'System', description: 'Match your OS preference' },
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
    const { theme, setTheme } = useTheme();
    const [showAi, setShowAi] = useState(settings.show_ai_features);
    const [hideToolbar, setHideToolbar] = useState(settings.hide_formatting_toolbar);

    const saveSetting = useCallback((key: string, value: boolean) => {
        fetch(update.url(), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify({ key, value }),
        }).then(() => {
            router.reload({ only: ['app_settings'] });
        });
    }, []);

    return (
        <SettingsLayout activeSection="appearance" book={book} title="Appearance">
            <div className="flex flex-col gap-6">
                <div>
                    <h1 className="text-[22px] font-semibold tracking-[-0.01em] text-ink">Appearance</h1>
                    <p className="mt-1 text-[14px] text-ink-muted">
                        Customize the look and behavior of Manuscript.
                    </p>
                </div>

                {/* Theme */}
                <div className="rounded-lg border border-border bg-surface-card p-6">
                    <div className="flex flex-col gap-4">
                        <div>
                            <span className="text-[15px] font-medium text-ink">Theme</span>
                            <p className="mt-0.5 text-[13px] text-ink-muted">Choose your preferred color scheme.</p>
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
                                    <span className="text-[14px] font-medium">{option.label}</span>
                                    <span className="mt-0.5 text-[12px] text-ink-muted">{option.description}</span>
                                </button>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Show AI Features */}
                <div className="rounded-lg border border-border bg-surface-card px-6">
                    <SettingRow
                        label="Show AI features"
                        description="Show AI panels, preparation, and analysis across the app."
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
                        Editor
                    </span>
                    <div className="mt-3 rounded-lg border border-border bg-surface-card px-6">
                        <SettingRow
                            label="Hide formatting toolbar"
                            description="Hide the toolbar at the top of the editor."
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

                {/* Version info */}
                <div className="rounded-lg border border-border bg-surface-card p-6">
                    <div className="flex flex-col gap-2">
                        <span className="text-[13px] font-medium text-ink-muted">Version</span>
                        <p className="text-[15px] text-ink">{version}</p>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    );
}
