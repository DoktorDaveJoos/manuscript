<?php

namespace App\Services\Export\Exporters;

use App\Contracts\Exporter;
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
    ) {}

    public function export(Book $book, Collection $chapters, ExportOptions $options): string
    {
        $trimSize = $options->trimSize ?? TrimSize::UsTrade;
        $fontSize = $options->fontSize ?? (int) config('export.pdf.default_font_size', 12);
        $dimensions = $trimSize->dimensions();
        $margins = $trimSize->margins();

        $defaultFont = $this->fontService->fontsAvailable() ? 'literata' : 'dejavuserif';

        $mpdfConfig = [
            'mode' => 'utf-8',
            'format' => [$dimensions['width'], $dimensions['height']],
            'margin_top' => $margins['top'],
            'margin_bottom' => $margins['bottom'],
            'margin_left' => $margins['gutter'],
            'margin_right' => $margins['outer'],
            'mirrorMargins' => true,
            'default_font_size' => $fontSize,
            'default_font' => $defaultFont,
            'tempDir' => storage_path('app/mpdf-tmp'),
        ];

        // Register Literata font if available (merge with mPDF defaults)
        if ($this->fontService->fontsAvailable()) {
            $defaultConfig = (new \Mpdf\Config\ConfigVariables)->getDefaults();
            $mpdfConfig['fontDir'] = array_merge($defaultConfig['fontDir'], [resource_path('fonts')]);

            $defaultFontConfig = (new \Mpdf\Config\FontVariables)->getDefaults();
            $mpdfConfig['fontdata'] = $defaultFontConfig['fontdata'] + $this->fontService->mPdfFontData();
        }

        $mpdf = new Mpdf($mpdfConfig);

        // Running header and footer
        $bookTitle = htmlspecialchars($book->title, ENT_HTML5, 'UTF-8');
        $mpdf->SetHTMLHeader("<div style=\"text-align: center; font-size: 9pt; color: #666; font-style: italic;\">{$bookTitle}</div>");
        $mpdf->SetHTMLFooter('<div style="text-align: center; font-size: 9pt; color: #666;">{PAGENO}</div>');

        $css = $this->buildCss($fontSize);
        $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);

        $html = $this->buildHtml($chapters, $options);
        $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);

        $filename = ExportService::tempPath('pdf');
        $mpdf->Output($filename, \Mpdf\Output\Destination::FILE);

        return $filename;
    }

    private function buildCss(int $fontSize): string
    {
        return <<<CSS
        body {
            font-family: literata, Georgia, serif;
            font-size: {$fontSize}pt;
            line-height: 1.5;
            text-align: justify;
        }
        h1 {
            font-size: 1.8em;
            font-weight: bold;
            text-align: center;
            margin: 2em 0 1em;
            page-break-before: always;
        }
        h1:first-child {
            page-break-before: avoid;
        }
        .act-break {
            font-size: 1.6em;
            font-weight: bold;
            text-align: center;
            margin: 3em 0 1em;
        }
        p {
            margin: 0;
            text-indent: 1.5em;
            widows: 2;
            orphans: 2;
        }
        p:first-child,
        .scene-break + p,
        h1 + p,
        .act-break + p {
            text-indent: 0;
        }
        .scene-break {
            text-align: center;
            font-style: italic;
            margin: 1.5em 0;
            text-indent: 0;
        }
        .toc-title {
            font-size: 1.8em;
            font-weight: bold;
            text-align: center;
            margin: 2em 0 1.5em;
        }
        .toc-entry {
            margin: 0.3em 0;
            text-indent: 0;
        }
        .toc-entry a {
            text-decoration: none;
            color: inherit;
        }
        CSS;
    }

    private function buildHtml(Collection $chapters, ExportOptions $options): string
    {
        $html = '';
        $currentActId = null;

        if ($options->includeTableOfContents && $chapters->isNotEmpty()) {
            $html .= $this->buildTocHtml($chapters, $options);
        }

        foreach ($chapters as $index => $chapter) {
            if ($options->includeActBreaks && $chapter->act_id && $chapter->act_id !== $currentActId) {
                $currentActId = $chapter->act_id;
                $actTitle = htmlspecialchars($chapter->act?->title ?? "Act {$chapter->act?->number}", ENT_HTML5, 'UTF-8');
                $html .= "<p class=\"act-break\">{$actTitle}</p>\n";
            }

            if ($options->includeChapterTitles) {
                $title = htmlspecialchars($chapter->title, ENT_HTML5, 'UTF-8');
                $html .= "<h1 id=\"chapter-{$index}\">{$title}</h1>\n";
            }

            $scenes = $chapter->scenes ?? collect();
            foreach ($scenes as $sceneIndex => $scene) {
                if ($sceneIndex > 0) {
                    $html .= "<p class=\"scene-break\">* * *</p>\n";
                }

                $content = $scene->content ?? '';
                $html .= $this->contentPreparer->toPdfHtml($content);
            }
        }

        return $html;
    }

    private function buildTocHtml(Collection $chapters, ExportOptions $options): string
    {
        $html = "<p class=\"toc-title\">Table of Contents</p>\n";

        foreach ($chapters as $index => $chapter) {
            $title = htmlspecialchars($chapter->title, ENT_HTML5, 'UTF-8');
            $html .= "<p class=\"toc-entry\"><a href=\"#chapter-{$index}\">{$title}</a></p>\n";
        }

        $html .= "<pagebreak />\n";

        return $html;
    }
}
