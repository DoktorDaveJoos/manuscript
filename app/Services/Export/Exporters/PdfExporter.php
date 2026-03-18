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
        $trimSize = $options->trimSize ?? TrimSize::UsTrade;
        $fontSize = $options->fontSize ?? 11;
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

        if ($this->fontService->fontsAvailable()) {
            $defaultConfig = (new \Mpdf\Config\ConfigVariables)->getDefaults();
            $mpdfConfig['fontDir'] = array_merge($defaultConfig['fontDir'], [resource_path('fonts')]);

            $defaultFontConfig = (new \Mpdf\Config\FontVariables)->getDefaults();
            $mpdfConfig['fontdata'] = $defaultFontConfig['fontdata'] + $this->fontService->mPdfFontData();
        }

        $mpdf = new Mpdf($mpdfConfig);

        $bookTitle = htmlspecialchars($book->title, ENT_HTML5, 'UTF-8');
        $bookAuthor = htmlspecialchars($book->author ?? '', ENT_HTML5, 'UTF-8');

        if ($options->showPageNumbers) {
            $this->setAlternatingFooters($mpdf);
        }
        $this->setAlternatingHeaders($mpdf, $bookTitle, $bookTitle);

        $template = new ClassicTemplate;
        $css = $template->pdfCss($fontSize);
        $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);

        // Front matter
        $this->writeFrontMatter($mpdf, $book, $bookTitle, $bookAuthor, $chapters, $options);

        // Chapters
        $this->writeChapters($mpdf, $bookTitle, $chapters, $options);

        // Back matter
        $this->writeBackMatter($mpdf, $options);

        $filename = ExportService::tempPath('pdf');
        $mpdf->Output($filename, \Mpdf\Output\Destination::FILE);

        return $filename;
    }

    private function writeFrontMatter(Mpdf $mpdf, Book $book, string $bookTitle, string $bookAuthor, Collection $chapters, ExportOptions $options): void
    {
        foreach ($options->frontMatter as $item) {
            match ($item) {
                'title-page' => $this->writeTitlePage($mpdf, $bookTitle, $bookAuthor),
                'copyright' => $this->writeCopyrightPage($mpdf, $bookTitle),
                'dedication' => $this->writeDedicationPage($mpdf, $options->dedicationText),
                'toc' => $this->writeTocFromFrontMatter($mpdf, $chapters, $options),
                default => null,
            };
        }
    }

    private function writeBackMatter(Mpdf $mpdf, ExportOptions $options): void
    {
        $headingMap = [
            'also-by' => 'Also By',
            'acknowledgments' => 'Acknowledgments',
            'about-author' => 'About the Author',
        ];
        $textMap = [
            'also-by' => $options->alsoByText,
            'acknowledgments' => $options->acknowledgmentText,
            'about-author' => $options->aboutAuthorText,
        ];

        foreach ($options->backMatter as $item) {
            if (isset($headingMap[$item])) {
                $this->writeBackMatterPage($mpdf, $headingMap[$item], $textMap[$item]);
            }
        }
    }

    private function writeTitlePage(Mpdf $mpdf, string $bookTitle, string $bookAuthor): void
    {
        $this->suppressHeaders($mpdf);
        $this->suppressFooters($mpdf);

        $html = <<<HTML
        <div style="text-align: center; padding-top: 35%;">
            <p class="title-page-title">{$bookTitle}</p>
        HTML;

        if ($bookAuthor !== '') {
            $html .= "\n    <p class=\"title-page-author\">{$bookAuthor}</p>";
        }

        $html .= "\n</div>\n<pagebreak />";

        $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);
    }

    private function writeCopyrightPage(Mpdf $mpdf, string $bookTitle): void
    {
        $this->suppressHeaders($mpdf);
        $this->suppressFooters($mpdf);

        $year = date('Y');
        $html = <<<HTML
        <div style="padding-top: 60%;">
            <p class="copyright-text">Copyright &copy; {$year}</p>
            <p class="copyright-text">{$bookTitle}</p>
            <p class="copyright-text">All rights reserved.</p>
        </div>
        <pagebreak />
        HTML;

        $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);
    }

    private function writeDedicationPage(Mpdf $mpdf, string $dedicationText): void
    {
        $this->suppressHeaders($mpdf);
        $this->suppressFooters($mpdf);

        $content = $this->contentPreparer->toMatterHtml($dedicationText);
        // If empty, render empty div; the CSS class handles italic/centering
        if ($content === '') {
            $content = '<p class="dedication-text"></p>';
        } else {
            // Wrap each paragraph in dedication styling
            $content = str_replace('class="matter-body"', 'class="dedication-text"', $content);
        }

        $html = <<<HTML
        <div style="text-align: center; padding-top: 30%;">
            {$content}
        </div>
        <pagebreak />
        HTML;

        $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);
    }

    private function writeTocFromFrontMatter(Mpdf $mpdf, Collection $chapters, ExportOptions $options): void
    {
        if ($chapters->isEmpty()) {
            return;
        }

        $this->suppressHeaders($mpdf);
        $this->suppressFooters($mpdf);

        $tocHtml = $this->buildTocHtml($chapters, $options);
        $mpdf->WriteHTML($tocHtml, \Mpdf\HTMLParserMode::HTML_BODY);
    }

    private function writeBackMatterPage(Mpdf $mpdf, string $heading, string $text): void
    {
        $this->suppressHeaders($mpdf);

        $body = $this->contentPreparer->toMatterHtml($text);
        $html = "<p class=\"matter-title\" style=\"page-break-before: always;\">{$heading}</p>\n{$body}";

        $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);
    }

    private function writeChapters(Mpdf $mpdf, string $bookTitle, Collection $chapters, ExportOptions $options): void
    {
        $currentActId = null;

        if ($options->includeTableOfContents && $chapters->isNotEmpty() && ! in_array('toc', $options->frontMatter)) {
            $tocHtml = $this->buildTocHtml($chapters, $options);
            $mpdf->WriteHTML($tocHtml, \Mpdf\HTMLParserMode::HTML_BODY);
        }

        foreach ($chapters as $index => $chapter) {
            $chapterTitle = htmlspecialchars($chapter->title, ENT_HTML5, 'UTF-8');
            $chapterHtml = '';

            if ($options->includeActBreaks && $chapter->act_id && $chapter->act_id !== $currentActId) {
                $currentActId = $chapter->act_id;
                $actTitle = htmlspecialchars($chapter->act?->title ?? "Act {$chapter->act?->number}", ENT_HTML5, 'UTF-8');
                $chapterHtml .= "<p class=\"act-break\">{$actTitle}</p>\n";
            }

            if ($options->includeChapterTitles) {
                $chapterNumber = $index + 1;
                $chapterHtml .= "<p class=\"chapter-label\" id=\"chapter-{$index}\">Chapter {$chapterNumber}</p>\n";
                $chapterHtml .= "<h1>{$chapterTitle}</h1>\n";
            }

            $scenes = $chapter->scenes ?? collect();
            $isFirstParagraph = true;

            foreach ($scenes as $sceneIndex => $scene) {
                if ($sceneIndex > 0) {
                    $chapterHtml .= "<p class=\"scene-break\">*&nbsp;&nbsp;*&nbsp;&nbsp;*</p>\n";
                    $isFirstParagraph = true;
                }

                $content = $scene->content ?? '';
                $html = $this->contentPreparer->toPdfHtml($content);

                if ($isFirstParagraph) {
                    $html = $this->addDropCap($html);
                    $isFirstParagraph = false;
                }

                $chapterHtml .= $html;
            }

            $this->suppressHeaders($mpdf);
            $mpdf->WriteHTML($chapterHtml, \Mpdf\HTMLParserMode::HTML_BODY);

            $this->setAlternatingHeaders($mpdf, $bookTitle, $chapterTitle);
        }
    }

    /**
     * Add a drop cap to the first character of the first paragraph.
     */
    private function addDropCap(string $html): string
    {
        return preg_replace(
            '/(<p[^>]*>)(\s*)([a-zA-Z\x{00C0}-\x{024F}])/u',
            '$1$2<span class="drop-cap">$3</span>',
            $html,
            1,
        );
    }

    private function setAlternatingHeaders(Mpdf $mpdf, string $bookTitle, string $chapterTitle): void
    {
        $mpdf->SetHTMLHeader(
            "<div style=\"text-align: left; font-size: 8pt; color: #B5B5B5; text-transform: uppercase; letter-spacing: 0.1em;\">{$bookTitle}</div>",
            'E',
        );
        $mpdf->SetHTMLHeader(
            "<div style=\"text-align: right; font-size: 8pt; color: #B5B5B5; text-transform: uppercase; letter-spacing: 0.1em;\">{$chapterTitle}</div>",
            'O',
        );
    }

    private function setAlternatingFooters(Mpdf $mpdf): void
    {
        $mpdf->SetHTMLFooter(
            '<div style="text-align: left; font-size: 8pt; color: #B5B5B5;">{PAGENO}</div>',
            'E',
        );
        $mpdf->SetHTMLFooter(
            '<div style="text-align: right; font-size: 8pt; color: #B5B5B5;">{PAGENO}</div>',
            'O',
        );
    }

    private function suppressHeaders(Mpdf $mpdf): void
    {
        $mpdf->SetHTMLHeader('', 'O');
        $mpdf->SetHTMLHeader('', 'E');
    }

    private function suppressFooters(Mpdf $mpdf): void
    {
        $mpdf->SetHTMLFooter('', 'O');
        $mpdf->SetHTMLFooter('', 'E');
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
