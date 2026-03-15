import { useTranslation } from 'react-i18next';
import SettingsLayout from '@/layouts/SettingsLayout';
import { downloadExport } from '@/lib/export-download';
import { useState, useCallback } from 'react';

type BookData = { id: number; title: string };
type StorylineRef = { id: number; name: string };

interface Props {
    book: BookData;
    storylines: StorylineRef[];
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
            ? 'bg-ink text-surface'
            : 'border border-border bg-surface-card text-ink-muted hover:border-ink hover:text-ink'
    }`;
}

function SectionLabel({ children }: { children: React.ReactNode }) {
    return (
        <span className="text-[11px] font-medium uppercase tracking-[0.08em] text-ink-faint">
            {children}
        </span>
    );
}

export default function Export({ book, storylines }: Props) {
    const { t } = useTranslation('settings');
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

        downloadExport(book, data)
            .catch(() => {})
            .finally(() => setExporting(false));
    }, [book, format, scope, storylineId, includeChapterTitles, includeActBreaks]);

    return (
        <SettingsLayout activeSection="export" book={book} title={t('export.pageTitle', { bookTitle: book.title })}>
            <div className="flex flex-col gap-4">
                <div>
                    <h1 className="text-[22px] font-semibold tracking-[-0.01em] text-ink">{t('export.title')}</h1>
                    <p className="mt-1 text-[13px] text-ink-muted">
                        {t('export.description')}
                    </p>
                </div>

                <div className="flex flex-col gap-5">
                    <div>
                        <SectionLabel>{t('export.format')}</SectionLabel>
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
                        <SectionLabel>{t('export.scope')}</SectionLabel>
                        <div className="mt-2 flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={() => setScope('full')}
                                className={pillClass(scope === 'full')}
                            >
                                {t('export.scope.full')}
                            </button>
                            <button
                                type="button"
                                onClick={() => setScope('chapter')}
                                className={pillClass(scope === 'chapter')}
                            >
                                {t('export.scope.chapter')}
                            </button>
                            {storylines.length > 1 && (
                                <button
                                    type="button"
                                    onClick={() => setScope('storyline')}
                                    className={pillClass(scope === 'storyline')}
                                >
                                    {t('export.scope.storyline')}
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
                        <SectionLabel>{t('export.options')}</SectionLabel>
                        <div className="mt-3 flex flex-col">
                            <div className="flex items-center justify-between border-b border-border-light py-3">
                                <span className="text-[14px] text-ink">{t('export.includeChapterTitles')}</span>
                                <Toggle checked={includeChapterTitles} onChange={() => setIncludeChapterTitles(!includeChapterTitles)} />
                            </div>
                            <div className="flex items-center justify-between py-3">
                                <span className="text-[14px] text-ink">{t('export.includeActBreaks')}</span>
                                <Toggle checked={includeActBreaks} onChange={() => setIncludeActBreaks(!includeActBreaks)} />
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        onClick={handleExport}
                        disabled={exporting}
                        className="h-10 rounded-md bg-ink px-6 text-[14px] font-medium text-surface transition-opacity hover:opacity-90 disabled:opacity-50"
                    >
                        {exporting ? t('export.exporting') : t('export.exportManuscript')}
                    </button>
                </div>
            </div>
        </SettingsLayout>
    );
}
