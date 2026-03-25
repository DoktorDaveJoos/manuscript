import { BookOpen, Download, Lock } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { VISUAL_FORMATS } from '@/components/export/types';
import type { Format, TrimSizeOption } from '@/components/export/types';
import Button from '@/components/ui/Button';
import SectionLabel from '@/components/ui/SectionLabel';
import Select from '@/components/ui/Select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/ToggleGroup';
import ToggleRow from '@/components/ui/ToggleRow';
import { useFreeTier } from '@/hooks/useFreeTier';

type ExportSettingsProps = {
    format: Format;
    onFormatChange: (f: Format) => void;
    template: string;
    onTemplateChange: (v: string) => void;
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
const TEMPLATES = [{ value: 'classic', label: 'Classic' }];
const FONT_SIZES = [10, 11, 12, 13, 14];

export default function ExportSettings({
    format,
    onFormatChange,
    template,
    onTemplateChange,
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
    const { canExportFormat } = useFreeTier();

    return (
        <div className="flex flex-1 flex-col overflow-y-auto bg-surface">
            <div className="flex flex-1 flex-col px-11 pt-10 pb-10">
                {/* Header */}
                <div className="flex flex-col gap-1.5">
                    <h1 className="text-xl font-semibold tracking-[-0.01em] text-ink">
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
                        <ToggleGroup
                            type="single"
                            value={format}
                            onValueChange={(val) => {
                                if (val) onFormatChange(val as Format);
                            }}
                        >
                            {FORMATS.map((f) => (
                                <ToggleGroupItem
                                    key={f}
                                    value={f}
                                    disabled={!canExportFormat(f)}
                                >
                                    .{f}
                                    {!canExportFormat(f) && <Lock size={10} />}
                                </ToggleGroupItem>
                            ))}
                        </ToggleGroup>
                        <p className="mt-1.5 text-[11px] text-ink-faint">
                            {t(`formatDescription.${format}`)}
                        </p>
                    </div>

                    {/* Template (visual formats only) */}
                    {VISUAL_FORMATS.has(format) && (
                        <div className="flex flex-col gap-2.5">
                            <SectionLabel>{t('template')}</SectionLabel>
                            <Select
                                value={template}
                                onChange={(e) =>
                                    onTemplateChange(e.target.value)
                                }
                                icon={<BookOpen />}
                            >
                                {TEMPLATES.map((tmpl) => (
                                    <option key={tmpl.value} value={tmpl.value}>
                                        {tmpl.label}
                                    </option>
                                ))}
                            </Select>
                            <p className="text-[11px] text-ink-faint">
                                {t('templateHint')}
                            </p>
                            <p className="rounded-md bg-accent/10 px-3 py-2 text-[11px] text-accent">
                                {t('templateComingSoon')}
                            </p>
                        </div>
                    )}

                    {/* PDF Options */}
                    {format === 'pdf' && (
                        <div className="flex flex-col gap-2.5">
                            <SectionLabel>{t('pdfOptions')}</SectionLabel>
                            <div className="flex flex-col gap-2.5">
                                <div className="flex items-center justify-between">
                                    <span className="text-[13px] text-ink-soft">
                                        {t('trimSize')}
                                    </span>
                                    <Select
                                        variant="compact"
                                        value={trimSize}
                                        onChange={(e) =>
                                            onTrimSizeChange(e.target.value)
                                        }
                                        className="w-auto"
                                    >
                                        {trimSizes.map((ts) => (
                                            <option
                                                key={ts.value}
                                                value={ts.value}
                                            >
                                                {ts.label}
                                            </option>
                                        ))}
                                    </Select>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-[13px] text-ink-soft">
                                        {t('fontSize')}
                                    </span>
                                    <Select
                                        variant="compact"
                                        value={String(fontSize)}
                                        onChange={(e) =>
                                            onFontSizeChange(
                                                Number(e.target.value),
                                            )
                                        }
                                        className="w-auto"
                                    >
                                        {FONT_SIZES.map((s) => (
                                            <option key={s} value={String(s)}>
                                                {s}pt
                                            </option>
                                        ))}
                                    </Select>
                                </div>
                            </div>
                            <p className="text-[11px] text-ink-faint">
                                {t('trimSizeHint')}
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
                    <Button
                        variant="primary"
                        size="lg"
                        onClick={onExport}
                        disabled={exporting}
                    >
                        <Download className="h-3.5 w-3.5" />
                        {exporting ? t('exporting') : t('exportAs', { format })}
                    </Button>
                </div>
            </div>
        </div>
    );
}
