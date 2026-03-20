import { BookOpen, ChevronDown, Download } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { TrimSizeOption } from '@/components/export/types';
import SectionLabel from '@/components/ui/SectionLabel';
import ToggleRow from '@/components/ui/ToggleRow';
import { cn } from '@/lib/utils';

export type Format = 'epub' | 'pdf' | 'docx' | 'txt';

type ExportSettingsProps = {
    format: Format;
    onFormatChange: (f: Format) => void;
    trimSize: string;
    onTrimSizeChange: (v: string) => void;
    fontSize: number;
    onFontSizeChange: (v: number) => void;
    trimSizes: TrimSizeOption[];
    includeChapterTitles: boolean;
    onIncludeChapterTitlesChange: () => void;
    includeActBreaks: boolean;
    onIncludeActBreaksChange: () => void;
    showPageNumbers: boolean;
    onShowPageNumbersChange: () => void;
    exporting: boolean;
    onExport: () => void;
};

const FORMATS: Format[] = ['epub', 'pdf', 'docx', 'txt'];
const FONT_SIZES = [10, 11, 12, 13, 14];

function FormatPill({
    label,
    active,
    onClick,
}: {
    label: string;
    active: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'rounded-md px-4 py-[7px] text-[12px] transition-colors',
                active
                    ? 'bg-ink font-semibold text-white dark:bg-ink dark:text-surface'
                    : 'bg-neutral-bg text-ink-muted hover:text-ink',
            )}
        >
            .{label}
        </button>
    );
}

function InlineDropdown({
    value,
    options,
    onChange,
}: {
    value: string;
    options: { value: string; label: string }[];
    onChange: (value: string) => void;
}) {
    return (
        <div className="relative inline-flex items-center gap-2 rounded-md border border-border-subtle bg-white px-3 py-2 dark:border-border dark:bg-surface-card">
            <select
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="absolute inset-0 cursor-pointer opacity-0"
            >
                {options.map((opt) => (
                    <option key={opt.value} value={opt.value}>
                        {opt.label}
                    </option>
                ))}
            </select>
            <span className="text-[12px] text-ink">
                {options.find((o) => o.value === value)?.label ?? value}
            </span>
            <ChevronDown className="h-3 w-3 text-ink-faint" />
        </div>
    );
}

export default function ExportSettings({
    format,
    onFormatChange,
    trimSize,
    onTrimSizeChange,
    fontSize,
    onFontSizeChange,
    trimSizes,
    includeChapterTitles,
    onIncludeChapterTitlesChange,
    includeActBreaks,
    onIncludeActBreaksChange,
    showPageNumbers,
    onShowPageNumbersChange,
    exporting,
    onExport,
}: ExportSettingsProps) {
    const { t } = useTranslation('export');

    return (
        <div className="flex flex-1 flex-col overflow-y-auto bg-surface">
            <div className="flex flex-1 flex-col px-11 pt-10 pb-10">
                {/* Header */}
                <div className="flex flex-col gap-1.5">
                    <h1 className="text-[22px] font-semibold tracking-[-0.01em] text-ink">
                        {t('title')}
                    </h1>
                    <p className="text-[13px] text-ink-faint">
                        {t('subtitle')}
                    </p>
                </div>

                <div className="mt-8 flex flex-col gap-8">
                    {/* Format */}
                    <div className="flex flex-col gap-2.5">
                        <SectionLabel>{t('format')}</SectionLabel>
                        <div className="flex gap-1.5">
                            {FORMATS.map((f) => (
                                <FormatPill
                                    key={f}
                                    label={f}
                                    active={format === f}
                                    onClick={() => onFormatChange(f)}
                                />
                            ))}
                        </div>
                    </div>

                    {/* Template */}
                    <div className="flex flex-col gap-2.5">
                        <SectionLabel>{t('template')}</SectionLabel>
                        <div className="flex items-center justify-between rounded-lg border border-border-subtle bg-white px-3.5 py-2.5 dark:border-border dark:bg-surface-card">
                            <div className="flex items-center gap-2.5">
                                <BookOpen className="h-4 w-4 text-ink-faint" />
                                <span className="text-[13px] text-ink">
                                    Classic
                                </span>
                            </div>
                            <ChevronDown className="h-3.5 w-3.5 text-ink-faint" />
                        </div>
                        <p className="text-[11px] text-ink-faint">
                            {t('templateHint')}
                        </p>
                    </div>

                    {/* PDF Options */}
                    {format === 'pdf' && (
                        <div className="flex flex-col gap-2.5">
                            <SectionLabel>{t('pdfOptions')}</SectionLabel>
                            <div className="flex flex-col gap-2.5">
                                <div className="flex items-center justify-between">
                                    <span className="text-[13px] text-ink-soft">
                                        {t('trimSize')}
                                    </span>
                                    <InlineDropdown
                                        value={trimSize}
                                        options={trimSizes}
                                        onChange={onTrimSizeChange}
                                    />
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-[13px] text-ink-soft">
                                        {t('fontSize')}
                                    </span>
                                    <InlineDropdown
                                        value={String(fontSize)}
                                        options={FONT_SIZES.map((s) => ({
                                            value: String(s),
                                            label: `${s}pt`,
                                        }))}
                                        onChange={(v) =>
                                            onFontSizeChange(Number(v))
                                        }
                                    />
                                </div>
                            </div>
                            <p className="text-[11px] text-ink-faint">
                                {t('pdfOptionsHint')}
                            </p>
                        </div>
                    )}

                    {/* Options toggles */}
                    <div className="flex flex-col">
                        <div className="pb-2.5">
                            <SectionLabel>{t('options')}</SectionLabel>
                        </div>
                        <ToggleRow
                            label={t('includeChapterTitles')}
                            checked={includeChapterTitles}
                            onChange={onIncludeChapterTitlesChange}
                        />
                        <ToggleRow
                            label={t('includeActBreaks')}
                            checked={includeActBreaks}
                            onChange={onIncludeActBreaksChange}
                        />
                        <ToggleRow
                            label={t('showPageNumbers')}
                            checked={showPageNumbers}
                            onChange={onShowPageNumbersChange}
                            border={false}
                        />
                    </div>
                </div>

                {/* Spacer */}
                <div className="flex-1" />

                {/* Export button + preview link */}
                <div className="flex items-center gap-3 pt-4">
                    <button
                        type="button"
                        onClick={onExport}
                        disabled={exporting}
                        className="flex items-center gap-2 rounded-lg bg-ink px-6 py-[11px] text-[13px] font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-50 dark:bg-ink dark:text-surface"
                    >
                        <Download className="h-3.5 w-3.5" />
                        {exporting ? t('exporting') : t('exportAs', { format })}
                    </button>
                    <span className="text-[12px] text-ink-faint">
                        {t('previewInBrowser')}
                    </span>
                </div>
            </div>
        </div>
    );
}
