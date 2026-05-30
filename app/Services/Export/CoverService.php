<?php

namespace App\Services\Export;

use App\Enums\FontPairing;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class CoverService
{
    /**
     * Fonts used on the cover: Cormorant Garamond (title) ships with the Elegant
     * pairing; Source Sans 3 (author / genre) ships with the Modern pairing.
     */
    private const COVER_PAIRINGS = [FontPairing::ElegantSerif, FontPairing::ModernMixed];

    /**
     * Default font key — the Garamond title face.
     */
    private const TITLE_FONT_KEY = 'cormorantgaramond';

    public function __construct(private FontService $fontService) {}

    /**
     * Which face of the jacket to render.
     */
    public const FACE_FRONT = 'front';

    public const FACE_BACK = 'back';

    public const FACE_WRAPAROUND = 'wraparound';

    /**
     * Render the cover as a single-page, print-ready PDF (raw bytes).
     *
     * @param  string  $face  One of FACE_FRONT (title/author panel — the default),
     *                        FACE_BACK (the blurb panel), or FACE_WRAPAROUND (the full
     *                        flattened jacket: back blurb + spine + front).
     */
    public function generatePdfString(CoverOptions $options, string $face = self::FACE_FRONT): string
    {
        return $this->buildMpdf($options, $face)->Output('', Destination::STRING_RETURN);
    }

    /**
     * Render the cover HTML for mPDF (exposed for testing the layout without the binary PDF).
     */
    public function renderHtml(CoverOptions $options, string $face = self::FACE_FRONT): string
    {
        return view('export.cover', ['options' => $options, 'face' => $face])->render();
    }

    private function buildMpdf(CoverOptions $options, string $face = self::FACE_FRONT): Mpdf
    {
        $mpdf = $this->createMpdf($options, $face);
        $mpdf->WriteHTML($this->renderHtml($options, $face));

        return $mpdf;
    }

    private function createMpdf(CoverOptions $options, string $face): Mpdf
    {
        // The wraparound enlarges the page to back+spine+front and places each panel with a
        // page-level absolutely-positioned block (see export/cover.blade.php). Those blocks
        // carry the bleed + safety inset in their own coordinates, so the page itself needs
        // no margin. The single-face front/back panels flow in the body and use a symmetric
        // safe-area inset as their page margin.
        if ($face === self::FACE_WRAPAROUND) {
            $dimensions = $options->wraparoundDimensions();
            $margins = ['top' => 0, 'bottom' => 0, 'left' => 0, 'right' => 0];
        } else {
            $dimensions = $options->dataDimensions();
            $edge = $options->contentMargin();
            $margins = ['top' => $edge, 'bottom' => $edge, 'left' => $edge, 'right' => $edge];
        }

        $defaultConfig = (new ConfigVariables)->getDefaults();
        $defaultFontConfig = (new FontVariables)->getDefaults();

        $fontDirs = $defaultConfig['fontDir'];
        $fontData = $defaultFontConfig['fontdata'];

        foreach (self::COVER_PAIRINGS as $pairing) {
            if ($this->fontService->fontsAvailableForPairing($pairing)) {
                $fontDirs = array_merge($fontDirs, $this->fontService->mPdfFontDirectories());
                $fontData = array_merge($fontData, $this->fontService->mPdfFontDataForPairing($pairing));
            }
        }

        $config = [
            'mode' => 'utf-8',
            'format' => [$dimensions['width'], $dimensions['height']],
            'margin_top' => $margins['top'],
            'margin_bottom' => $margins['bottom'],
            'margin_left' => $margins['left'],
            'margin_right' => $margins['right'],
            'margin_header' => 0,
            'margin_footer' => 0,
            'default_font' => self::TITLE_FONT_KEY,
            'fontDir' => $fontDirs,
            'fontdata' => $fontData,
            'tempDir' => storage_path('app/mpdf-tmp'),
        ];

        @mkdir(storage_path('app/mpdf-tmp'), 0755, true);

        return new Mpdf($config);
    }
}
