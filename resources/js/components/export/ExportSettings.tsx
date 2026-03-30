import { Download, Lock } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import CustomizePanel from '@/components/export/CustomizePanel';
import TemplateSelector from '@/components/export/TemplateSelector';
import { VISUAL_FORMATS } from '@/components/export/types';
import type {
    FontPairingDef,
    Format,
    SceneBreakStyleDef,
    TemplateDef,
    TrimSizeOption,
} from '@/components/export/types';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/Alert';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import PageHeader from '@/components/ui/PageHeader';
import SectionLabel from '@/components/ui/SectionLabel';
import Select from '@/components/ui/Select';
import { Spinner } from '@/components/ui/spinner';
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
    templates: TemplateDef[];
    fontPairings: FontPairingDef[];
    sceneBreakStyles: SceneBreakStyleDef[];
    fontPairing: string;
    onFontPairingChange: (v: string) => void;
    sceneBreakStyle: string;
    onSceneBreakStyleChange: (v: string) => void;
    dropCaps: boolean;
    onDropCapsChange: (v: boolean) => void;
    isCustomized: boolean;
    includeCover: boolean;
    onIncludeCoverChange: (v: boolean) => void;
    hasCover: boolean;
};

const FORMATS: Format[] = ['epub', 'pdf', 'docx', 'txt'];
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
    templates,
    fontPairings,
    sceneBreakStyles,
    fontPairing,
    onFontPairingChange,
    sceneBreakStyle,
    onSceneBreakStyleChange,
    dropCaps,
    onDropCapsChange,
    isCustomized,
    includeCover,
    onIncludeCoverChange,
    hasCover,
}: ExportSettingsProps) {
    const { t } = useTranslation('export');
    const { canExportFormat } = useFreeTier();
    const isVisual = VISUAL_FORMATS.has(format);

    return (
        <div className="flex flex-1 flex-col overflow-y-auto bg-surface">
            <div className="flex flex-1 flex-col px-11 pt-10 pb-10">
                {/* Header */}
                <PageHeader title={t('title')} subtitle={t('subtitle')} />

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
                                    <span className="inline-flex items-center gap-1">
                                        .{f}
                                        {!canExportFormat(f) && (
                                            <Lock size={10} />
                                        )}
                                    </span>
                                </ToggleGroupItem>
                            ))}
                        </ToggleGroup>
                        <p className="mt-1.5 text-[11px] text-ink-faint">
                            {t(`formatDescription.${format}`)}
                        </p>
                    </div>

                    {/* Template (visual formats only) */}
                    {isVisual && (
                        <div className="flex flex-col gap-2.5">
                            <div className="flex items-center gap-2">
                                <SectionLabel>{t('template')}</SectionLabel>
                                {isCustomized && (
                                    <Badge
                                        variant="warning"
                                        className="text-[10px]"
                                    >
                                        {t('customLabel')}
                                    </Badge>
                                )}
                            </div>
                            <TemplateSelector
                                templates={templates}
                                selectedTemplate={template}
                                onChange={onTemplateChange}
                            />
                            <Alert variant="info">
                                <AlertTitle>
                                    {t('templateAlertTitle')}
                                </AlertTitle>
                                <AlertDescription>
                                    {t('templateAlertDescription')}
                                </AlertDescription>
                            </Alert>

                            {/* Customize Panel */}
                            <CustomizePanel
                                fontPairings={fontPairings}
                                sceneBreakStyles={sceneBreakStyles}
                                selectedFontPairing={fontPairing}
                                selectedSceneBreakStyle={sceneBreakStyle}
                                dropCaps={dropCaps}
                                onFontPairingChange={onFontPairingChange}
                                onSceneBreakStyleChange={
                                    onSceneBreakStyleChange
                                }
                                onDropCapsChange={onDropCapsChange}
                                isCustomized={isCustomized}
                            />
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
                        />
                        {isVisual && (
                            <ToggleRow
                                label={
                                    hasCover
                                        ? t('includeCover')
                                        : t('noCoverUploaded')
                                }
                                checked={includeCover}
                                onChange={() =>
                                    onIncludeCoverChange(!includeCover)
                                }
                                border={false}
                            />
                        )}
                        {!isVisual && (
                            <div className="h-0" /> // spacer for non-visual last row
                        )}
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
                        {exporting ? (
                            <Spinner className="h-3.5 w-3.5" />
                        ) : (
                            <Download className="h-3.5 w-3.5" />
                        )}
                        {exporting ? t('exporting') : t('exportAs', { format })}
                    </Button>
                </div>
            </div>
        </div>
    );
}
