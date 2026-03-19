<?php

namespace App\Services\Export\Exporters;

use App\Contracts\Exporter;
use App\Models\Book;
use App\Services\Export\ContentPreparer;
use App\Services\Export\ExportOptions;
use App\Services\Export\ExportService;
use App\Services\Export\FontService;
use Illuminate\Support\Collection;
use Native\Desktop\Facades\System;

class PdfExporter implements Exporter
{
    public function __construct(
        private ContentPreparer $contentPreparer,
        private FontService $fontService,
    ) {}

    public function export(Book $book, Collection $chapters, ExportOptions $options): string
    {
        $html = $this->renderHtml($book, $chapters, $options);

        $pdfBase64 = System::printToPDF($html, [
            'preferCSSPageSize' => true,
            'printBackground' => true,
            'margins' => ['top' => 0, 'bottom' => 0, 'left' => 0, 'right' => 0],
        ]);

        $filename = ExportService::tempPath('pdf');
        file_put_contents($filename, base64_decode($pdfBase64));

        return $filename;
    }

    /**
     * Render the complete HTML document for Chromium PDF generation.
     */
    public function renderHtml(Book $book, Collection $chapters, ExportOptions $options): string
    {
        $preparedChapters = $this->prepareChapters($chapters);
        $fonts = $this->fontService->fontsAvailable()
            ? $this->fontService->base64FontData()
            : null;

        return view('export.pdf', [
            'book' => $book,
            'chapters' => $preparedChapters,
            'options' => $options,
            'fonts' => $fonts,
        ])->render();
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

                if ($sceneIndex === 0) {
                    $html = $this->addDropCap($html);
                }

                $preparedContent .= $html;
            }

            $chapter->prepared_content = $preparedContent;

            return $chapter;
        });
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
}
