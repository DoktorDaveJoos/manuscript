<?php

namespace App\Services\Export\Templates;

use App\Contracts\ExportTemplate;
use App\Enums\FontPairing;
use App\Enums\SceneBreakStyle;

class RomanceTemplate implements ExportTemplate
{
    public function slug(): string
    {
        return 'romance';
    }

    public function name(): string
    {
        return 'Romance';
    }

    /**
     * Flat array of all design tokens — serialized to frontend via Inertia.
     *
     * @return array<string, mixed>
     */
    public function designTokens(): array
    {
        return [
            'bodyColor' => '#2a2a2a',
            'headingColor' => '#1a1a1a',
            'accentColor' => '#8b7355',
            'pdfLineHeight' => 1.4,
            'epubLineHeight' => 1.55,
            'chapterLabelSizeEm' => 0.65,
            'titleSizeEm' => 2.0,
            'titleWeight' => 'normal',
            'runningHeaderStyle' => 'italic',
            'runningHeaderColor' => '#8b7355',
            'runningHeaderSizePt' => 8,
            'pageNumberColor' => '#8b7355',
            'pageNumberSizePt' => 8,
            'pageNumberPosition' => 'alternating',
        ];
    }

    public function defaultFontPairing(): FontPairing
    {
        return FontPairing::ElegantSerif;
    }

    public function defaultSceneBreakStyle(): SceneBreakStyle
    {
        return SceneBreakStyle::Flourish;
    }

    public function defaultDropCaps(): bool
    {
        return true;
    }

    /**
     * Complete CSS for mPDF rendering.
     */
    public function pdfCss(int $fontSize, ?FontPairing $fontPairing = null): string
    {
        return $this->baseCss($fontSize, 1.4, fontPairing: $fontPairing);
    }

    /**
     * CSS for e-book preview PDF — reflowable look, no running headers or page numbers.
     */
    public function ebookPreviewCss(int $fontSize, ?FontPairing $fontPairing = null): string
    {
        return $this->baseCss($fontSize, 1.55, pagedMedia: false, fontPairing: $fontPairing);
    }

    /**
     * Shared CSS for both PDF and e-book preview rendering.
     */
    private function baseCss(int $fontSize, float $lineHeight, bool $pagedMedia = true, ?FontPairing $fontPairing = null): string
    {
        $pairing = $fontPairing ?? $this->defaultFontPairing();
        $bodyFontKey = $pairing->bodyFontKey();
        $headingFontKey = $pairing->headingFontKey();
        $bodyFontFamily = "{$bodyFontKey}, Georgia, serif";
        $headingFontFamily = "{$headingFontKey}, Georgia, serif";

        $matterSectionPage = $pagedMedia ? "\n            page: matter;" : '';

        return <<<CSS
        body {
            font-family: {$bodyFontFamily};
            font-size: {$fontSize}pt;
            line-height: {$lineHeight};
            text-align: justify;
            color: #2a2a2a;
        }
        .chapter-label {
            font-size: 0.65em;
            font-weight: 500;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: #8b7355;
            margin: 2em 0 0.25em;
            text-indent: 0;
        }
        h1 {
            font-family: {$headingFontFamily};
            font-size: 2.0em;
            font-weight: normal;
            text-align: center;
            margin: 0 0 1.5em;
            color: #1a1a1a;
        }
        .act-break {
            font-size: 2.0em;
            font-weight: bold;
            text-align: center;
            margin: 3em 0 1em;
            color: #1a1a1a;
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
            letter-spacing: 0.3em;
            color: #8b7355;
            margin: 1.5em 0;
            text-indent: 0;
        }
        .toc-title {
            font-size: 2.0em;
            font-weight: bold;
            text-align: center;
            margin: 2em 0 1.5em;
            color: #1a1a1a;
        }
        .toc-entry {
            margin: 0.3em 0;
            text-indent: 0;
        }
        .toc-entry a {
            text-decoration: none;
            color: inherit;
        }
        .matter-title {
            font-size: 0.65em;
            font-weight: 500;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: #8b7355;
            margin: 2em 0 1.5em;
            text-indent: 0;
        }
        .matter-body {
            text-indent: 0;
            margin: 0 0 0.5em;
        }
        .title-page-title {
            font-size: 2.2em;
            font-weight: normal;
            text-align: center;
            color: #1a1a1a;
            margin: 0;
            text-indent: 0;
        }
        .title-page-author {
            font-size: 0.85em;
            text-align: center;
            color: #8b7355;
            margin: 0.5em 0 0;
            text-indent: 0;
        }
        .copyright-text {
            font-size: 0.75em;
            text-align: center;
            color: #8b7355;
            text-indent: 0;
            margin: 0 0 0.3em;
        }
        .dedication-text {
            font-style: italic;
            text-align: center;
            color: #2a2a2a;
            text-indent: 0;
        }
        .matter-section {{$matterSectionPage}
            break-before: page;
        }
        .chapter-section {
            break-before: page;
        }
        CSS;
    }

    /**
     * Complete CSS for EPUB rendering.
     */
    public function epubCss(string $fontFaceCss, ?FontPairing $fontPairing = null): string
    {
        $pairing = $fontPairing ?? $this->defaultFontPairing();
        $bodyFamily = $pairing->bodyFontFamily();
        $headingFamily = $pairing->headingFontFamily();

        return <<<CSS
        {$fontFaceCss}

        body {
            font-family: {$bodyFamily};
            font-size: 1em;
            line-height: 1.55; /* keep in sync with getLineHeightMultiplier() in usePreviewPages.ts */
            margin: 1em;
            text-align: justify;
            hyphens: auto;
            -webkit-hyphens: auto;
        }
        h1 {
            font-family: {$headingFamily};
            font-size: 2.0em;
            font-weight: normal;
            margin: 2em 0 1em;
            text-align: center;
            page-break-before: always;
        }
        h1:first-child {
            page-break-before: avoid;
        }
        h2 {
            font-family: {$headingFamily};
            font-size: 1.4em;
            font-weight: normal;
            margin: 2em 0 0.5em;
            text-align: center;
        }
        p {
            margin: 0;
            text-indent: 1.5em;
            widows: 2;
            orphans: 2;
        }
        p:first-child,
        hr.scene-break + p,
        h1 + p,
        h2 + p {
            text-indent: 0;
        }
        hr.scene-break {
            border: none;
            text-align: center;
            margin: 1.5em 0;
        }
        hr.scene-break::after {
            content: "~❋~";
            color: #8b7355;
        }
        .act-break {
            font-size: 2.0em;
            font-weight: 700;
            text-align: center;
            margin: 3em 0 1em;
        }
        nav#toc ol {
            list-style: none;
            padding: 0;
        }
        nav#toc ol li {
            margin: 0.5em 0;
        }
        nav#toc ol li a {
            text-decoration: none;
            color: inherit;
        }
        .title-page {
            text-align: center;
            margin-top: 40%;
        }
        .title-page h1 {
            font-size: 2.2em;
            font-weight: normal;
            page-break-before: avoid;
            margin: 0;
        }
        .title-page .author {
            font-size: 0.85em;
            color: #8b7355;
            margin-top: 0.5em;
        }
        .copyright-page {
            margin-top: 60%;
            text-align: center;
            font-size: 0.75em;
            color: #8b7355;
        }
        .copyright-page p {
            text-indent: 0;
            margin: 0 0 0.3em;
        }
        .dedication-page {
            text-align: center;
            margin-top: 30%;
            font-style: italic;
        }
        .dedication-page p {
            text-indent: 0;
        }
        .matter-title {
            font-size: 0.65em;
            font-weight: 500;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: #8b7355;
            margin: 2em 0 1.5em;
        }
        .matter-body p {
            text-indent: 0;
            margin: 0 0 0.5em;
        }
        CSS;
    }

    public function sceneBreakCss(): string
    {
        return <<<'CSS'
        .scene-break {
            text-align: center;
            margin: 1.5em 0;
            font-size: 1em;
            color: #8b7355;
            page-break-before: avoid;
            page-break-after: avoid;
            text-indent: 0;
        }
        .scene-break--rule {
            border: none;
            border-top: 1px solid #8b7355;
            width: 30%;
            margin: 1.5em auto;
        }
        .scene-break--blank {
            height: 2em;
        }
        CSS;
    }

    public function dropCapCss(): string
    {
        return <<<'CSS'
        .drop-cap {
            float: left;
            font-size: 3.2em;
            line-height: 0.8;
            padding-right: 0.08em;
            margin-top: 0.05em;
            font-weight: bold;
            color: #8b7355;
        }
        CSS;
    }
}
