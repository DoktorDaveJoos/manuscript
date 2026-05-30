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
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

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
            ->Output($outputPath, Destination::FILE);
    }

    /**
     * Generate a PDF and return the raw bytes.
     */
    public function generatePdfString(Book $book, Collection $chapters, ExportOptions $options): string
    {
        return $this->buildMpdf($book, $chapters, $options)
            ->Output('', Destination::STRING_RETURN);
    }

    /**
     * Render HTML and write it into a configured mPDF instance.
     */
    private function buildMpdf(Book $book, Collection $chapters, ExportOptions $options): Mpdf
    {
        $isEbookPreview = in_array($options->previewFormat, [ExportFormat::Epub, ExportFormat::Kdp], true);
        $html = $this->renderHtml($book, $chapters, $options, $isEbookPreview);
        $locale = $book->language ?? config('app.fallback_locale', 'en');
        $mpdf = $this->createMpdf($options, $isEbookPreview, $locale);
        $mpdf->WriteHTML($html);

        return $mpdf;
    }

    /**
     * Languages mPDF ships hyphenation patterns for. Falls back to English
     * for unsupported locales so we never hyphenate with the wrong dictionary.
     */
    private const HYPHENATION_LANGUAGES = ['en', 'de', 'es', 'fi', 'fr', 'it', 'nl', 'pl', 'ru', 'sv'];

    /**
     * Resolve the mPDF hyphenation pattern language for a book locale, falling
     * back to English when mPDF has no patterns for it.
     */
    public static function hyphenationLanguage(string $locale): string
    {
        return in_array($locale, self::HYPHENATION_LANGUAGES, true) ? $locale : 'en';
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

        // For print-ready CMYK output, render body copy as true K-only black
        // (0,0,0,100) rather than the screen-tuned soft black (~#2a2a2a, which
        // would convert to a lighter K). Headings/labels stay grey and convert
        // to K-only grey automatically under restrictColorSpace.
        if ($options->cmyk && ! $isEbookPreview) {
            $css .= "\nbody { color: cmyk(0, 0, 0, 100); }";
        }

        $previousLocale = app()->getLocale();
        app()->setLocale($book->language ?? config('app.fallback_locale', 'en'));

        try {
            return view('export.pdf', [
                'book' => $book,
                'chapters' => $preparedChapters,
                'options' => $options,
                'css' => $css,
                'isEbookPreview' => $isEbookPreview,
                'contentPreparer' => $this->contentPreparer,
                'template' => $this->template,
            ])->render();
        } finally {
            app()->setLocale($previousLocale);
        }
    }

    /**
     * Resolve the print page geometry (millimetres) for an export: the trim
     * size — either a preset or user-supplied custom dimensions — grown by the
     * configured bleed on every side, with each margin shifted by the same
     * bleed so the text block stays put relative to the trim edge.
     *
     * @return array{width: float, height: float, margins: array{top: float, bottom: float, outer: float, gutter: float}}
     */
    public static function resolveGeometry(ExportOptions $options): array
    {
        if ($options->customWidth !== null && $options->customHeight !== null) {
            $width = $options->customWidth;
            $height = $options->customHeight;
            $margins = TrimSize::defaultMarginsFor($width, $height);
        } else {
            $trimSize = $options->trimSize ?? TrimSize::UsTrade;
            $dimensions = $trimSize->dimensions();
            $width = $dimensions['width'];
            $height = $dimensions['height'];
            $margins = $trimSize->margins();
        }

        $bleed = max(0.0, $options->bleed);

        if ($bleed > 0) {
            $width += 2 * $bleed;
            $height += 2 * $bleed;
            $margins = [
                'top' => $margins['top'] + $bleed,
                'bottom' => $margins['bottom'] + $bleed,
                'outer' => $margins['outer'] + $bleed,
                'gutter' => $margins['gutter'] + $bleed,
            ];
        }

        return ['width' => $width, 'height' => $height, 'margins' => $margins];
    }

    /**
     * Create a configured mPDF instance.
     */
    private function createMpdf(ExportOptions $options, bool $isEbookPreview = false, string $locale = 'en'): Mpdf
    {
        $fontSize = $options->fontSize;

        if ($isEbookPreview) {
            // E-reader dimensions (~Kindle Paperwhite proportions)
            $dimensions = ['width' => 90, 'height' => 122];
            $margins = ['top' => 10, 'bottom' => 10, 'gutter' => 10, 'outer' => 10];
        } else {
            $geometry = self::resolveGeometry($options);
            $dimensions = ['width' => $geometry['width'], 'height' => $geometry['height']];
            $margins = $geometry['margins'];
        }

        $defaultConfig = (new ConfigVariables)->getDefaults();
        $defaultFontConfig = (new FontVariables)->getDefaults();

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
            // Hyphenate using the book's own language dictionary. Without this mPDF
            // defaults to English patterns, mangling e.g. German compounds
            // ("Geschwindigkeitsbe-grenzung" instead of "Geschwindigkeits-begrenzung").
            // Keep at least 3 characters on each side of a break. NOTE: SHYcharmin must
            // stay <= 4 — a value of 5 silently disables hyphenation in this mPDF version.
            'SHYlang' => self::hyphenationLanguage($locale),
            'SHYleftmin' => 3,
            'SHYrightmin' => 3,
            'SHYcharmin' => 4,
        ];

        // Print-ready PDF: force the whole document into the CMYK colour space.
        // Every template colour is a neutral grey (r=g=b), which mPDF's RGB→CMYK
        // conversion maps to K-only ink (C=M=Y=0), so black stays on the K plate.
        if ($options->cmyk && ! $isEbookPreview) {
            $config['restrictColorSpace'] = 3;
        }

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
