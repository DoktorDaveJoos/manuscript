<?php

namespace App\Services\Export\Templates;

use App\Contracts\ExportTemplate;
use App\Enums\FontPairing;
use App\Enums\SceneBreakStyle;

class ClassicTemplate implements ExportTemplate
{
    public function slug(): string
    {
        return 'classic';
    }

    public function name(): string
    {
        return 'Classic';
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
            'accentColor' => '#999999',
            'pdfLineHeight' => 1.35,
            'epubLineHeight' => 1.5,
            'chapterLabelSizeEm' => 0.7,
            'titleSizeEm' => 1.6,
            'titleWeight' => 'normal',
            'runningHeaderStyle' => 'italic',
            'runningHeaderColor' => '#999999',
            'runningHeaderSizePt' => 8,
            'pageNumberColor' => '#999999',
            'pageNumberSizePt' => 8,
            'pageNumberPosition' => 'alternating',
        ];
    }

    public function defaultFontPairing(): FontPairing
    {
        return FontPairing::ClassicSerif;
    }

    public function defaultSceneBreakStyle(): SceneBreakStyle
    {
        return SceneBreakStyle::Asterisks;
    }

    public function defaultDropCaps(): bool
    {
        return false;
    }

    /**
     * Complete CSS for mPDF rendering.
     */
    public function pdfCss(int $fontSize, ?FontPairing $fontPairing = null): string
    {
        return $this->baseCss($fontSize, 1.35, fontPairing: $fontPairing);
    }

    /**
     * CSS for e-book preview PDF — reflowable look, no running headers or page numbers.
     */
    public function ebookPreviewCss(int $fontSize, ?FontPairing $fontPairing = null): string
    {
        return $this->baseCss($fontSize, 1.5, pagedMedia: false, fontPairing: $fontPairing);
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
            font-size: 0.7em;
            font-weight: 500;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: #999999;
            margin: 2em 0 0.25em;
            text-indent: 0;
        }
        h1 {
            font-family: {$headingFontFamily};
            font-size: 1.6em;
            font-weight: normal;
            text-align: center;
            margin: 0 0 1.5em;
            color: #1a1a1a;
        }
        .act-break {
            font-size: 1.6em;
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
            color: #999999;
            margin: 1.5em 0;
            text-indent: 0;
        }
        .toc-title {
            font-size: 1.8em;
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
            font-size: 0.7em;
            font-weight: 500;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: #999999;
            margin: 2em 0 1.5em;
            text-indent: 0;
        }
        .matter-body {
            text-indent: 0;
            margin: 0 0 0.5em;
        }
        .title-page-title {
            font-size: 2em;
            font-weight: normal;
            text-align: center;
            color: #1a1a1a;
            margin: 0;
            text-indent: 0;
        }
        .title-page-author {
            font-size: 0.85em;
            text-align: center;
            color: #999999;
            margin: 0.5em 0 0;
            text-indent: 0;
        }
        .copyright-text {
            font-size: 0.75em;
            text-align: center;
            color: #999;
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
            line-height: 1.5; /* keep in sync with getLineHeightMultiplier() in usePreviewPages.ts */
            margin: 1em;
            text-align: justify;
            hyphens: auto;
            -webkit-hyphens: auto;
        }
        h1 {
            font-family: {$headingFamily};
            font-size: 1.8em;
            font-weight: 700;
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
            font-weight: 700;
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
            content: "* * *";
            font-style: italic;
        }
        .act-break {
            font-size: 1.6em;
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
            font-size: 2em;
            font-weight: normal;
            page-break-before: avoid;
            margin: 0;
        }
        .title-page .author {
            font-size: 0.85em;
            color: #999999;
            margin-top: 0.5em;
        }
        .copyright-page {
            margin-top: 60%;
            text-align: center;
            font-size: 0.75em;
            color: #999;
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
            font-size: 0.7em;
            font-weight: 500;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: #999999;
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
            color: #999999;
            page-break-before: avoid;
            page-break-after: avoid;
            text-indent: 0;
        }
        .scene-break--rule {
            border: none;
            border-top: 1px solid #cccccc;
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
            color: #1a1a1a;
        }
        CSS;
    }
}
