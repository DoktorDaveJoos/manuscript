import { useTheme } from '@/hooks/useTheme';
import SettingsLayout from '@/layouts/SettingsLayout';
import type { Theme } from '@/lib/theme';

interface Props {
    version: string;
    book?: { id: number; title: string } | null;
}

const THEME_OPTIONS: { value: Theme; label: string; description: string }[] = [
    { value: 'light', label: 'Light', description: 'Always use light mode' },
    { value: 'dark', label: 'Dark', description: 'Always use dark mode' },
    { value: 'system', label: 'System', description: 'Match your OS preference' },
];

export default function About({ version, book }: Props) {
    const { theme, setTheme } = useTheme();

    return (
        <SettingsLayout activeSection="about" book={book} title="About">
            <div className="flex flex-col gap-6">
                <div>
                    <h1 className="text-[22px] font-semibold tracking-[-0.01em] text-ink">About</h1>
                    <p className="mt-1 text-[14px] text-ink-muted">
                        Application information.
                    </p>
                </div>

                {/* Appearance */}
                <div className="rounded-lg border border-border bg-surface-card p-6">
                    <div className="flex flex-col gap-4">
                        <div>
                            <span className="text-[15px] font-medium text-ink">Appearance</span>
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

                <div className="rounded-lg border border-border bg-surface-card p-6">
                    <div className="flex flex-col gap-4">
                        <div>
                            <span className="text-[13px] font-medium text-ink-muted">Application</span>
                            <p className="mt-0.5 text-[15px] font-medium text-ink">Manuscript</p>
                        </div>
                        <div>
                            <span className="text-[13px] font-medium text-ink-muted">Version</span>
                            <p className="mt-0.5 text-[15px] text-ink">{version}</p>
                        </div>
                        <div>
                            <span className="text-[13px] font-medium text-ink-muted">Description</span>
                            <p className="mt-0.5 text-[14px] leading-relaxed text-ink-muted">
                                A desktop application for authors to write, organize, and polish manuscripts with AI assistance.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    );
}
