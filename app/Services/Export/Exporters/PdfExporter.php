<?php

namespace App\Services\Export\Exporters;

use App\Contracts\Exporter;
use App\Contracts\ExportTemplate;
use App\Enums\ExportFormat;
use App\Enums\TrimSize;
use App\Models\Book;
use App\Services\Export\ContentPreparer;
use App\Services\Export\ExportOptions;
use App\Services\Export\ExportService;
use App\Services\Export\FontService;
use Illuminate\Support\Collection;
use Mpdf\Mpdf;

class PdfExporter implements Exporter
{
    public function __construct(
        private ContentPreparer $contentPreparer,
        private FontService $fontService,
        private ExportTemplate $template,
    ) {}

    public function export(Book $book, Collection $chapters, ExportOptions $options): string
    {
        $filename = ExportService::tempPath('pdf');
        $this->generatePdf($book, $chapters, $options, $filename);

        return $filename;
    }

    /**
     * Generate a PDF and write it to the given path.
     */
    public function generatePdf(Book $book, Collection $chapters, ExportOptions $options, string $outputPath): void
    {
        $this->buildMpdf($book, $chapters, $options)
            ->Output($outputPath, \Mpdf\Output\Destination::FILE);
    }

    /**
     * Generate a PDF and return the raw bytes.
     */
    public function generatePdfString(Book $book, Collection $chapters, ExportOptions $options): string
    {
        return $this->buildMpdf($book, $chapters, $options)
            ->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    }

    /**
     * Render HTML and write it into a configured mPDF instance.
     */
    private function buildMpdf(Book $book, Collection $chapters, ExportOptions $options): Mpdf
    {
        $isEbookPreview = in_array($options->previewFormat, [ExportFormat::Epub, ExportFormat::Kdp], true);
        $html = $this->renderHtml($book, $chapters, $options, $isEbookPreview);
        $mpdf = $this->createMpdf($options, $isEbookPreview);
        $mpdf->WriteHTML($html);

        return $mpdf;
    }

    /**
     * Render the HTML body content for mPDF (no doctype/head — mPDF handles that).
     */
    public function renderHtml(Book $book, Collection $chapters, ExportOptions $options, bool $isEbookPreview = false): string
    {
        $preparedChapters = $this->prepareChapters($chapters, $options);

        $pairing = $options->fontPairing ?? $this->template->defaultFontPairing();
        $fontSize = $options->fontSize;
        $css = $isEbookPreview
            ? $this->template->ebookPreviewCss($fontSize, $pairing)
            : $this->template->pdfCss($fontSize, $pairing);

        $css .= "\n".$this->template->sceneBreakCss();

        if ($options->dropCaps) {
            $css .= "\n".$this->template->dropCapCss();
        }

        return view('export.pdf', [
            'book' => $book,
            'chapters' => $preparedChapters,
            'options' => $options,
            'css' => $css,
            'isEbookPreview' => $isEbookPreview,
            'contentPreparer' => $this->contentPreparer,
            'template' => $this->template,
        ])->render();
    }

    /**
     * Create a configured mPDF instance.
     */
    private function createMpdf(ExportOptions $options, bool $isEbookPreview = false): Mpdf
    {
        $fontSize = $options->fontSize;

        if ($isEbookPreview) {
            // E-reader dimensions (~Kindle Paperwhite proportions)
            $dimensions = ['width' => 90, 'height' => 122];
            $margins = ['top' => 10, 'bottom' => 10, 'gutter' => 10, 'outer' => 10];
        } else {
            $trimSize = $options->trimSize ?? TrimSize::UsTrade;
            $dimensions = $trimSize->dimensions();
            $margins = $trimSize->margins();
        }

        $defaultConfig = (new \Mpdf\Config\ConfigVariables)->getDefaults();
        $defaultFontConfig = (new \Mpdf\Config\FontVariables)->getDefaults();

        $fontDirs = $defaultConfig['fontDir'];
        $fontData = $defaultFontConfig['fontdata'];

        $pairing = $options->fontPairing ?? $this->template->defaultFontPairing();
        $bodyFontKey = $pairing->bodyFontKey();

        if ($this->fontService->fontsAvailableForPairing($pairing)) {
            $fontDirs = array_merge($fontDirs, $this->fontService->mPdfFontDirectories());
            $fontData = array_merge($fontData, $this->fontService->mPdfFontDataForPairing($pairing));
        }

        $config = [
            'mode' => 'utf-8',
            'format' => [$dimensions['width'], $dimensions['height']],
            'margin_top' => $margins['top'],
            'margin_bottom' => $margins['bottom'],
            'margin_left' => $margins['gutter'],
            'margin_right' => $margins['outer'],
            'margin_header' => $isEbookPreview ? 0 : 5,
            'margin_footer' => $isEbookPreview ? 0 : 5,
            'default_font_size' => $fontSize,
            'default_font' => $bodyFontKey,
            'fontDir' => $fontDirs,
            'fontdata' => $fontData,
            'tempDir' => storage_path('app/mpdf-tmp'),
        ];

        @mkdir(storage_path('app/mpdf-tmp'), 0755, true);

        return new Mpdf($config);
    }

    /**
     * Prepare chapter content: merge scenes with scene breaks and apply drop caps.
     */
    private function prepareChapters(Collection $chapters, ExportOptions $options): Collection
    {
        $sceneBreak = $options->sceneBreakStyle ?? $this->template->defaultSceneBreakStyle();
        $dropCaps = $options->dropCaps;

        return $chapters->map(function ($chapter) use ($sceneBreak, $dropCaps) {
            $scenes = $chapter->scenes ?? collect();
            $preparedContent = '';

            foreach ($scenes as $sceneIndex => $scene) {
                if ($sceneIndex > 0) {
                    $preparedContent .= $sceneBreak->html();
                }

                $content = $scene->content ?? '';
                $html = $this->contentPreparer->toChapterHtml($content, $sceneBreak);

                $preparedContent .= $html;
            }

            if ($dropCaps) {
                $preparedContent = $this->contentPreparer->addDropCap($preparedContent);
            }

            $chapter->prepared_content = $preparedContent;

            return $chapter;
        });
    }
}
