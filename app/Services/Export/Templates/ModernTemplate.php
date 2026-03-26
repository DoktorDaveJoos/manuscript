<?php

namespace App\Services\Export\Templates;

use App\Contracts\ExportTemplate;
use App\Enums\FontPairing;
use App\Enums\SceneBreakStyle;

class ModernTemplate implements ExportTemplate
{
    public function slug(): string
    {
        return 'modern';
    }

    public function name(): string
    {
        return 'Modern';
    }

    /**
     * Flat array of all design tokens — serialized to frontend via Inertia.
     *
     * @return array<string, mixed>
     */
    public function designTokens(): array
    {
        return [
            'bodyColor' => '#333333',
            'headingColor' => '#111111',
            'pdfLineHeight' => 1.4,
            'epubLineHeight' => 1.6,
            'chapterLabelSizeEm' => 1.0,
            'titleSizeEm' => 1.2,
            'titleWeight' => 'normal',
            'runningHeaderStyle' => 'normal',
            'runningHeaderColor' => '#aaaaaa',
            'runningHeaderSizePt' => 7,
            'pageNumberColor' => '#aaaaaa',
            'pageNumberSizePt' => 7,
            'pageNumberPosition' => 'alternating',
        ];
    }

    public function defaultFontPairing(): FontPairing
    {
        return FontPairing::ModernMixed;
    }

    public function defaultSceneBreakStyle(): SceneBreakStyle
    {
        return SceneBreakStyle::Rule;
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
        return $this->baseCss($fontSize, 1.4, fontPairing: $fontPairing);
    }

    /**
     * CSS for e-book preview PDF — reflowable look, no running headers or page numbers.
     */
    public function ebookPreviewCss(int $fontSize, ?FontPairing $fontPairing = null): string
    {
        return $this->baseCss($fontSize, 1.6, pagedMedia: false, fontPairing: $fontPairing);
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
        $headingFontFamily = "{$headingFontKey}, 'Helvetica Neue', sans-serif";

        $matterSectionPage = $pagedMedia ? "\n            page: matter;" : '';

        return <<<CSS
        body {
            font-family: {$bodyFontFamily};
            font-size: {$fontSize}pt;
            line-height: {$lineHeight};
            text-align: justify;
            color: #333333;
            hyphens: auto;
            -webkit-hyphens: auto;
        }
        .chapter-label {
            font-size: 1em;
            font-weight: bold;
            text-align: left;
            color: #333333;
            margin: 4.5em 0 0.3em;
            text-indent: 0;
        }
        h1 {
            font-family: {$headingFontFamily};
            font-size: 1.2em;
            font-weight: normal;
            text-align: left;
            margin: 0 0 1.5em;
            color: #111111;
        }
        .act-break {
            font-size: 1.8em;
            font-weight: bold;
            text-align: center;
            margin: 3em 0 1em;
            color: #111111;
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
            color: #888888;
            margin: 1.5em 0;
            text-indent: 0;
        }
        .toc-title {
            font-size: 1.2em;
            font-weight: normal;
            text-align: left;
            margin: 4.5em 0 1.5em;
            color: #111111;
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
            font-size: 0.85em;
            font-weight: 500;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: #888888;
            margin: 4.5em 0 1.5em;
            text-indent: 0;
        }
        .matter-body {
            text-indent: 0;
            margin: 0 0 0.5em;
        }
        .title-page-title {
            font-size: 2em;
            font-weight: bold;
            text-align: center;
            color: #111111;
            margin: 0;
            text-indent: 0;
        }
        .title-page-author {
            font-size: 0.85em;
            text-align: center;
            color: #888888;
            margin: 0.5em 0 0;
            text-indent: 0;
        }
        .copyright-text {
            font-size: 0.75em;
            text-align: center;
            color: #888;
            text-indent: 0;
            margin: 0 0 0.3em;
        }
        .dedication-text {
            font-style: italic;
            text-align: center;
            color: #333333;
            text-indent: 0;
        }
        .matter-section {{$matterSectionPage}
            break-before: page;
        }
        .chapter-section {
            page-break-before: always;
            break-before: page;
        }
        blockquote {
            margin: 1em 0 1em 2em;
            font-size: 0.95em;
            font-style: italic;
        }
        blockquote p {
            text-indent: 0;
        }
        ul, ol {
            margin: 0.8em 0 0.8em 2em;
            padding: 0;
        }
        li {
            margin: 0.2em 0;
            text-indent: 0;
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
            line-height: 1.6; /* keep in sync with getLineHeightMultiplier() in usePreviewPages.ts */
            margin: 1em;
            text-align: justify;
            hyphens: auto;
            -webkit-hyphens: auto;
        }
        .chapter-label {
            font-size: 1em;
            font-weight: bold;
            text-align: left;
            color: #333333;
            margin: 4.5em 0 0.3em;
        }
        h1 {
            font-family: {$headingFamily};
            font-size: 1.2em;
            font-weight: normal;
            margin: 0 0 1em;
            text-align: left;
            page-break-before: always;
        }
        h1:first-child {
            page-break-before: avoid;
        }
        .chapter-label + h1 {
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
            border-top: 1px solid #cccccc;
            width: 30%;
            margin: 1.5em auto;
        }
        .act-break {
            font-size: 1.8em;
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
            font-weight: bold;
            page-break-before: avoid;
            margin: 0;
        }
        .title-page .author {
            font-size: 0.85em;
            color: #888888;
            margin-top: 0.5em;
        }
        .copyright-page {
            margin-top: 60%;
            text-align: center;
            font-size: 0.75em;
            color: #888;
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
            color: #888888;
            margin: 2em 0 1.5em;
        }
        .matter-body p {
            text-indent: 0;
            margin: 0 0 0.5em;
        }
        blockquote {
            margin: 1em 0 1em 2em;
            font-size: 0.95em;
            font-style: italic;
        }
        blockquote p {
            text-indent: 0;
        }
        ul, ol {
            margin: 0.8em 0 0.8em 2em;
            padding: 0;
        }
        li {
            margin: 0.2em 0;
            text-indent: 0;
        }
        CSS;
    }

    public function sceneBreakCss(): string
    {
        return <<<'CSS'
        .scene-break {
            margin: 1.5em 0;
            text-indent: 0;
            page-break-before: avoid;
            page-break-after: avoid;
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
        return '';
    }

    public function chapterHeaderHtml(int $index, string $title, string $locale = 'en'): string
    {
        $number = $index + 1;
        $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        return '<p class="chapter-label" id="chapter-'.$index.'">'.$number.'</p>'
            ."\n".'<h1>'.$escapedTitle.'</h1>';
    }
}
