<?php

namespace App\Services\Export\Exporters;

use App\Contracts\Exporter;
use App\Enums\TrimSize;
use App\Models\Book;
use App\Services\Export\ContentPreparer;
use App\Services\Export\ExportOptions;
use App\Services\Export\ExportService;
use App\Services\Export\FontService;
use App\Services\Export\Templates\ClassicTemplate;
use Illuminate\Support\Collection;
use Mpdf\Mpdf;

class PdfExporter implements Exporter
{
    public function __construct(
        private ContentPreparer $contentPreparer,
        private FontService $fontService,
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
        $html = $this->renderHtml($book, $chapters, $options);
        $mpdf = $this->createMpdf($options);
        $mpdf->WriteHTML($html);

        return $mpdf;
    }

    /**
     * Render the HTML body content for mPDF (no doctype/head — mPDF handles that).
     */
    public function renderHtml(Book $book, Collection $chapters, ExportOptions $options): string
    {
        $preparedChapters = $this->prepareChapters($chapters);

        $fontSize = $options->fontSize;

        return view('export.pdf', [
            'book' => $book,
            'chapters' => $preparedChapters,
            'options' => $options,
            'css' => (new ClassicTemplate)->pdfCss($fontSize),
            'contentPreparer' => $this->contentPreparer,
        ])->render();
    }

    /**
     * Create a configured mPDF instance.
     */
    private function createMpdf(ExportOptions $options): Mpdf
    {
        $trimSize = $options->trimSize ?? TrimSize::UsTrade;
        $dimensions = $trimSize->dimensions();
        $margins = $trimSize->margins();
        $fontSize = $options->fontSize;

        $defaultConfig = (new \Mpdf\Config\ConfigVariables)->getDefaults();
        $defaultFontConfig = (new \Mpdf\Config\FontVariables)->getDefaults();

        $fontDirs = $defaultConfig['fontDir'];
        $fontData = $defaultFontConfig['fontdata'];

        if ($this->fontService->fontsAvailable()) {
            $fontDirs[] = resource_path('fonts');
            $fontData = array_merge($fontData, $this->fontService->mPdfFontData());
        }

        $config = [
            'mode' => 'utf-8',
            'format' => [$dimensions['width'], $dimensions['height']],
            'margin_top' => $margins['top'],
            'margin_bottom' => $margins['bottom'],
            'margin_left' => $margins['gutter'],
            'margin_right' => $margins['outer'],
            'margin_header' => 5,
            'margin_footer' => 5,
            'default_font_size' => $fontSize,
            'default_font' => 'crimsonpro',
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
    private function prepareChapters(Collection $chapters): Collection
    {
        return $chapters->map(function ($chapter) {
            $scenes = $chapter->scenes ?? collect();
            $preparedContent = '';

            foreach ($scenes as $sceneIndex => $scene) {
                if ($sceneIndex > 0) {
                    $preparedContent .= '<p class="scene-break">*&nbsp;&nbsp;*&nbsp;&nbsp;*</p>';
                }

                $content = $scene->content ?? '';
                $html = $this->contentPreparer->toPdfHtml($content);

                $preparedContent .= $html;
            }

            $chapter->prepared_content = $preparedContent;

            return $chapter;
        });
    }
}
