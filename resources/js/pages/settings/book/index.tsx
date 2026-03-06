import {
    updateAiModel,
    updateWritingStyle,
    regenerateWritingStyle,
    updateProsePassRules,
    doExport,
} from '@/actions/App/Http/Controllers/BookSettingsController';
import SettingsLayout from '@/layouts/SettingsLayout';
import { getXsrfToken } from '@/lib/csrf';
import type { AiProvider, ProsePassRule } from '@/types/models';
import { router } from '@inertiajs/react';
import { useState, useCallback } from 'react';

type BookData = {
    id: number;
    title: string;
    ai_provider: AiProvider;
    ai_model: string | null;
    ai_enabled: boolean;
    writing_style_text: string | null;
};

type EnabledProvider = {
    provider: AiProvider;
    label: string;
    text_model: string | null;
};

type StorylineRef = { id: number; name: string };

interface Props {
    book: BookData;
    enabled_providers: EnabledProvider[];
    writing_style_display: string;
    rules: ProsePassRule[];
    storylines: StorylineRef[];
}

function SectionLabel({ children }: { children: React.ReactNode }) {
    return (
        <span className="text-[11px] font-medium uppercase tracking-[0.08em] text-ink-faint">
            {children}
        </span>
    );
}

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

function pillClass(active: boolean): string {
    return `rounded-full px-3.5 py-1.5 text-[13px] font-medium transition-colors ${
        active
            ? 'bg-ink text-white'
            : 'border border-border bg-surface-card text-ink-muted hover:border-ink hover:text-ink'
    }`;
}

function AiModelSection({ book, enabled_providers }: { book: BookData; enabled_providers: EnabledProvider[] }) {
    const [aiEnabled, setAiEnabled] = useState(book.ai_enabled);
    const [provider, setProvider] = useState<AiProvider>(book.ai_provider);
    const [model, setModel] = useState(book.ai_model ?? '');
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState('');

    const save = useCallback(
        (data: Record<string, unknown>) => {
            setSaving(true);
            setMessage('');

            fetch(updateAiModel.url(book), {
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
                    setMessage(json.message);
                    router.reload({ only: ['book'] });
                    setTimeout(() => setMessage(''), 3000);
                })
                .catch(() => setMessage('Failed to save.'))
                .finally(() => setSaving(false));
        },
        [book],
    );

    const handleToggleAi = () => {
        const newEnabled = !aiEnabled;
        setAiEnabled(newEnabled);
        save({ ai_enabled: newEnabled });
    };

    const handleProviderChange = (p: AiProvider) => {
        setProvider(p);
        save({ ai_provider: p });
    };

    const handleSaveModel = () => {
        save({ ai_model: model || null });
    };

    return (
        <section id="ai-model" className="scroll-mt-12">
            <SectionLabel>AI Model</SectionLabel>
            <div className="mt-4 flex flex-col gap-5">
                <div className="flex items-center justify-between">
                    <div>
                        <span className="text-[15px] font-medium text-ink">AI Features</span>
                        <p className="mt-0.5 text-[13px] text-ink-muted">
                            Enable AI-powered revision and analysis for this book.
                        </p>
                    </div>
                    <Toggle checked={aiEnabled} onChange={handleToggleAi} />
                </div>

                {aiEnabled && (
                    <>
                        <div>
                            <SectionLabel>Provider</SectionLabel>
                            <p className="mt-1 text-[13px] text-ink-muted">
                                Select the AI provider for this book.
                            </p>
                            {enabled_providers.length === 0 ? (
                                <p className="mt-2.5 text-[13px] text-ink-faint">
                                    No providers enabled. Configure providers in License & AI settings.
                                </p>
                            ) : (
                                <div className="mt-2.5 flex flex-wrap gap-2">
                                    {enabled_providers.map((p) => (
                                        <button
                                            key={p.provider}
                                            type="button"
                                            onClick={() => handleProviderChange(p.provider)}
                                            className={pillClass(provider === p.provider)}
                                        >
                                            {p.label}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div>
                            <SectionLabel>Active Model</SectionLabel>
                            <p className="mt-1 text-[12px] text-ink-faint">
                                Override the default model for this book. Leave empty to use the provider default.
                            </p>
                            <div className="mt-2 flex gap-3">
                                <input
                                    type="text"
                                    value={model}
                                    onChange={(e) => setModel(e.target.value)}
                                    placeholder="e.g. claude-sonnet-4-20250514"
                                    className="h-9 flex-1 rounded-md border border-border bg-surface px-3 text-[13px] text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none"
                                />
                                <button
                                    type="button"
                                    onClick={handleSaveModel}
                                    disabled={saving}
                                    className="h-9 rounded-md bg-ink px-4 text-[13px] font-medium text-white transition-opacity hover:opacity-90 disabled:opacity-50"
                                >
                                    {saving ? 'Saving...' : 'Save'}
                                </button>
                            </div>
                            {message && (
                                <span className="mt-2 block text-[12px] font-medium text-status-final">{message}</span>
                            )}
                        </div>

                        {enabled_providers.length > 0 && (
                            <p className="text-[13px] text-status-final">
                                <span className="mr-1">●</span>
                                Connected — AI features are enabled
                            </p>
                        )}
                    </>
                )}
            </div>
        </section>
    );
}

function WritingStyleSection({ book, writing_style_display }: { book: BookData; writing_style_display: string }) {
    const [text, setText] = useState(book.writing_style_text ?? writing_style_display);
    const [saving, setSaving] = useState(false);
    const [regenerating, setRegenerating] = useState(false);
    const [message, setMessage] = useState('');

    const handleSave = useCallback(() => {
        setSaving(true);
        setMessage('');

        fetch(updateWritingStyle.url(book), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify({ writing_style_text: text }),
        })
            .then(async (res) => {
                if (!res.ok) throw new Error('Save failed');
                const json = await res.json();
                setMessage(json.message);
                setTimeout(() => setMessage(''), 3000);
            })
            .catch(() => setMessage('Failed to save.'))
            .finally(() => setSaving(false));
    }, [book, text]);

    const handleRegenerate = useCallback(() => {
        setRegenerating(true);
        setMessage('');

        fetch(regenerateWritingStyle.url(book), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
                Accept: 'application/json',
            },
        })
            .then(async (res) => {
                const json = await res.json();
                if (!res.ok) {
                    setMessage(json.message || 'Failed to regenerate.');
                    return;
                }
                setText(json.writing_style_text);
                setMessage(json.message);
                setTimeout(() => setMessage(''), 3000);
            })
            .catch(() => setMessage('Failed to regenerate writing style.'))
            .finally(() => setRegenerating(false));
    }, [book]);

    return (
        <section id="writing-style" className="scroll-mt-12">
            <div className="flex items-start justify-between">
                <div>
                    <SectionLabel>Writing Style</SectionLabel>
                    <p className="mt-1 text-[13px] text-ink-muted">
                        Describe the writing style for AI to follow when revising this book.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={handleRegenerate}
                    disabled={regenerating}
                    className="h-8 rounded-md border border-border px-3.5 text-[13px] font-medium text-ink transition-colors hover:bg-neutral-bg disabled:opacity-50"
                >
                    {regenerating ? 'Regenerating...' : 'Regenerate'}
                </button>
            </div>

            <div className="mt-4">
                <textarea
                    value={text}
                    onChange={(e) => setText(e.target.value)}
                    onBlur={handleSave}
                    placeholder="Describe the writing style, tone, POV, and distinctive features..."
                    rows={10}
                    className="w-full resize-y rounded-md border border-border bg-surface px-3 py-2.5 text-[14px] leading-relaxed text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none"
                />
                {(message || saving) && (
                    <span className="mt-2 block text-[12px] font-medium text-status-final">
                        {saving ? 'Saving...' : message}
                    </span>
                )}
            </div>
        </section>
    );
}

function ProsePassRulesSection({ book, rules: initialRules }: { book: BookData; rules: ProsePassRule[] }) {
    const [rules, setRules] = useState(initialRules);

    const toggleRule = useCallback(
        (key: string) => {
            const updated = rules.map((r) =>
                r.key === key ? { ...r, enabled: !r.enabled } : r,
            );
            setRules(updated);

            fetch(updateProsePassRules.url(book), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({ rules: updated }),
            }).catch(() => {
                setRules(rules);
            });
        },
        [book, rules],
    );

    return (
        <section id="prose-pass-rules" className="scroll-mt-12">
            <SectionLabel>Prose Pass Rules</SectionLabel>
            <p className="mt-1 text-[13px] text-ink-muted">
                Control which revision rules AI applies when editing prose.
            </p>

            <div className="mt-4 flex flex-col">
                {rules.map((rule, i) => (
                    <div
                        key={rule.key}
                        className={`flex items-start gap-4 py-3.5 ${
                            i < rules.length - 1 ? 'border-b border-border-light' : ''
                        }`}
                    >
                        <div className="pt-0.5">
                            <Toggle checked={rule.enabled} onChange={() => toggleRule(rule.key)} />
                        </div>
                        <div className="flex-1">
                            <span className="text-[14px] font-medium text-ink">{rule.label}</span>
                            <p className="mt-0.5 text-[13px] text-ink-muted">{rule.description}</p>
                        </div>
                    </div>
                ))}
            </div>
        </section>
    );
}

function ExportSection({ book, storylines }: { book: BookData; storylines: StorylineRef[] }) {
    type Format = 'docx' | 'txt';
    type Scope = 'full' | 'chapter' | 'storyline';

    const [format, setFormat] = useState<Format>('docx');
    const [scope, setScope] = useState<Scope>('full');
    const [storylineId, setStorylineId] = useState<number | null>(storylines[0]?.id ?? null);
    const [includeChapterTitles, setIncludeChapterTitles] = useState(true);
    const [includeActBreaks, setIncludeActBreaks] = useState(false);
    const [exporting, setExporting] = useState(false);

    const handleExport = useCallback(() => {
        setExporting(true);

        const data: Record<string, unknown> = {
            format,
            scope,
            include_chapter_titles: includeChapterTitles,
            include_act_breaks: includeActBreaks,
        };
        if (scope === 'storyline' && storylineId) data.storyline_id = storylineId;

        fetch(doExport.url(book), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
                Accept: 'application/octet-stream',
            },
            body: JSON.stringify(data),
        })
            .then(async (res) => {
                if (!res.ok) throw new Error('Export failed');
                const blob = await res.blob();
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `${book.title}.${format}`;
                a.click();
                URL.revokeObjectURL(url);
            })
            .catch(() => {})
            .finally(() => setExporting(false));
    }, [book, format, scope, storylineId, includeChapterTitles, includeActBreaks]);

    return (
        <section id="export" className="scroll-mt-12">
            <SectionLabel>Export</SectionLabel>
            <p className="mt-1 text-[13px] text-ink-muted">
                Export your manuscript as a file.
            </p>

            <div className="mt-5 flex flex-col gap-5">
                <div>
                    <SectionLabel>Format</SectionLabel>
                    <div className="mt-2 flex gap-2">
                        {(['docx', 'txt'] as Format[]).map((f) => (
                            <button
                                key={f}
                                type="button"
                                onClick={() => setFormat(f)}
                                className={pillClass(format === f)}
                            >
                                .{f}
                            </button>
                        ))}
                    </div>
                </div>

                <div>
                    <SectionLabel>Scope</SectionLabel>
                    <div className="mt-2 flex flex-wrap gap-2">
                        <button
                            type="button"
                            onClick={() => setScope('full')}
                            className={pillClass(scope === 'full')}
                        >
                            Full manuscript
                        </button>
                        <button
                            type="button"
                            onClick={() => setScope('chapter')}
                            className={pillClass(scope === 'chapter')}
                        >
                            Current chapter
                        </button>
                        {storylines.length > 1 && (
                            <button
                                type="button"
                                onClick={() => setScope('storyline')}
                                className={pillClass(scope === 'storyline')}
                            >
                                Selected storyline
                            </button>
                        )}
                    </div>

                    {scope === 'storyline' && storylines.length > 1 && (
                        <select
                            value={storylineId ?? ''}
                            onChange={(e) => setStorylineId(Number(e.target.value))}
                            className="mt-3 h-9 rounded-md border border-border bg-surface px-3 text-[13px] text-ink focus:border-accent focus:outline-none"
                        >
                            {storylines.map((s) => (
                                <option key={s.id} value={s.id}>{s.name}</option>
                            ))}
                        </select>
                    )}
                </div>

                <div>
                    <SectionLabel>Options</SectionLabel>
                    <div className="mt-3 flex flex-col">
                        <div className="flex items-center justify-between border-b border-border-light py-3">
                            <span className="text-[14px] text-ink">Include chapter titles</span>
                            <Toggle checked={includeChapterTitles} onChange={() => setIncludeChapterTitles(!includeChapterTitles)} />
                        </div>
                        <div className="flex items-center justify-between py-3">
                            <span className="text-[14px] text-ink">Include act breaks</span>
                            <Toggle checked={includeActBreaks} onChange={() => setIncludeActBreaks(!includeActBreaks)} />
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    onClick={handleExport}
                    disabled={exporting}
                    className="h-10 rounded-md bg-ink px-6 text-[14px] font-medium text-white transition-opacity hover:opacity-90 disabled:opacity-50"
                >
                    {exporting ? 'Exporting...' : 'Export manuscript'}
                </button>
            </div>
        </section>
    );
}

export default function BookSettings({ book, enabled_providers, writing_style_display, rules, storylines }: Props) {
    return (
        <SettingsLayout activeSection="book-settings" book={book} title={`Settings - ${book.title}`}>
            <div className="flex flex-col">
                <AiModelSection book={book} enabled_providers={enabled_providers} />
                <hr className="my-8 border-border-light" />
                <WritingStyleSection book={book} writing_style_display={writing_style_display} />
                <hr className="my-8 border-border-light" />
                <ProsePassRulesSection book={book} rules={rules} />
                <hr className="my-8 border-border-light" />
                <ExportSection book={book} storylines={storylines} />
            </div>
        </SettingsLayout>
    );
}
