import { Link } from '@inertiajs/react';
import { ArrowRight, Download } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { show as showDesigner } from '@/actions/App/Http/Controllers/BookDesignController';
import TemplateSelector from '@/components/export/TemplateSelector';
import { VISUAL_FORMATS } from '@/components/export/types';
import type {
    DocxLayout,
    Format,
    TemplateDef,
} from '@/components/export/types';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import PageHeader from '@/components/ui/PageHeader';
import SectionLabel from '@/components/ui/SectionLabel';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/ToggleGroup';
import ToggleRow from '@/components/ui/ToggleRow';

type ExportSettingsProps = {
    bookId: number;
    format: Format;
    onFormatChange: (f: Format) => void;
    template: string;
    onTemplateChange: (v: string) => void;
    templates: TemplateDef[];
    docxLayout: DocxLayout;
    onDocxLayoutChange: (v: DocxLayout) => void;
    cmyk: boolean;
    onCmykChange: (v: boolean) => void;
    includeCover: boolean;
    onIncludeCoverChange: (v: boolean) => void;
    hasCover: boolean;
    exporting: boolean;
    onExport: () => void;
    error: string | null;
};

const FORMATS: Format[] = ['epub', 'pdf', 'docx', 'txt'];

export default function ExportSettings({
    bookId,
    format,
    onFormatChange,
    template,
    onTemplateChange,
    templates,
    docxLayout,
    onDocxLayoutChange,
    cmyk,
    onCmykChange,
    includeCover,
    onIncludeCoverChange,
    hasCover,
    exporting,
    onExport,
    error,
}: ExportSettingsProps) {
    const { t } = useTranslation('export');
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
                                    data-testid={`export-format-${f}`}
                                >
                                    .{f}
                                </ToggleGroupItem>
                            ))}
                        </ToggleGroup>
                        <p className="mt-1.5 text-[11px] text-ink-faint">
                            {t(`formatDescription.${format}`)}
                        </p>
                    </div>

                    {/* Page layout (.docx only) — international manuscript vs. Normseite */}
                    {format === 'docx' && (
                        <div className="flex flex-col gap-2.5">
                            <SectionLabel>{t('layout')}</SectionLabel>
                            <ToggleGroup
                                type="single"
                                value={docxLayout}
                                onValueChange={(val) => {
                                    if (val)
                                        onDocxLayoutChange(val as DocxLayout);
                                }}
                            >
                                <ToggleGroupItem
                                    value="manuscript"
                                    data-testid="docx-layout-manuscript"
                                >
                                    {t('docxLayout.manuscript')}
                                </ToggleGroupItem>
                                <ToggleGroupItem
                                    value="normseite"
                                    data-testid="docx-layout-normseite"
                                >
                                    {t('docxLayout.normseite')}
                                </ToggleGroupItem>
                            </ToggleGroup>
                            <p className="mt-1.5 text-[11px] text-ink-faint">
                                {t(`docxLayoutDescription.${docxLayout}`)}
                            </p>
                        </div>
                    )}

                    {/* Template (visual formats only) */}
                    {isVisual && (
                        <div className="flex flex-col gap-2.5">
                            <SectionLabel>{t('template')}</SectionLabel>
                            <TemplateSelector
                                templates={templates}
                                selectedTemplate={template}
                                onChange={onTemplateChange}
                            />
                            <Link
                                href={showDesigner.url(bookId)}
                                className="inline-flex items-center gap-1 self-start text-[12px] text-ink-muted transition-colors hover:text-ink"
                                data-testid="export-customize-link"
                            >
                                {t('customizeInDesigner')}
                                <ArrowRight className="size-3" />
                            </Link>
                        </div>
                    )}

                    {/* Options */}
                    {isVisual && (
                        <div className="flex flex-col">
                            <div className="pb-2.5">
                                <SectionLabel>{t('options')}</SectionLabel>
                            </div>
                            {format === 'pdf' && (
                                <ToggleRow
                                    label={t('cmyk')}
                                    checked={cmyk}
                                    onChange={() => onCmykChange(!cmyk)}
                                />
                            )}
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
                            {format === 'pdf' && (
                                <p className="mt-1.5 text-[11px] text-ink-faint">
                                    {t('cmykHint')}
                                </p>
                            )}
                        </div>
                    )}
                </div>

                {/* Spacer */}
                <div className="flex-1" />

                {/* Export button + error */}
                <div className="flex flex-col items-start gap-3 pt-4">
                    {error && (
                        <Alert variant="destructive">
                            <AlertTitle>{t('exportError')}</AlertTitle>
                            <AlertDescription>{error}</AlertDescription>
                        </Alert>
                    )}
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
