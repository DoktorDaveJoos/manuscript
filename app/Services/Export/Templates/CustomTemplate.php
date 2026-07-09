<?php

namespace App\Services\Export\Templates;

use App\Contracts\ExportTemplate;
use App\Enums\FontPairing;
use App\Enums\SceneBreakStyle;

/**
 * A user-designed template from the Book Designer: wraps a built-in base
 * template and overrides its typography via CSS generated from the stored
 * settings. Page geometry overrides are merged into the export options by
 * ExportService::applyDesignTemplate() rather than handled here.
 */
class CustomTemplate implements ExportTemplate
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        private ExportTemplate $base,
        private array $settings,
        private string $name,
        private ?int $id = null,
    ) {}

    public function slug(): string
    {
        return $this->id !== null ? 'custom:'.$this->id : 'custom';
    }

    public function name(): string
    {
        return $this->name;
    }

    public function designTokens(): array
    {
        return $this->base->designTokens();
    }

    public function designSettings(): array
    {
        return array_replace_recursive($this->base->designSettings(), $this->settings);
    }

    public function defaultFontPairing(): FontPairing
    {
        $pairing = $this->settings['typography']['font_pairing'] ?? null;

        return ($pairing !== null ? FontPairing::tryFrom($pairing) : null) ?? $this->base->defaultFontPairing();
    }

    public function defaultSceneBreakStyle(): SceneBreakStyle
    {
        $style = $this->settings['headings']['scene_break_style'] ?? null;

        return ($style !== null ? SceneBreakStyle::tryFrom($style) : null) ?? $this->base->defaultSceneBreakStyle();
    }

    public function defaultDropCaps(): bool
    {
        return (bool) ($this->settings['headings']['drop_caps'] ?? $this->base->defaultDropCaps());
    }

    public function pdfCss(int $fontSize, ?FontPairing $fontPairing = null): string
    {
        return $this->base->pdfCss($fontSize, $fontPairing ?? $this->defaultFontPairing())
            ."\n".$this->overrideCss();
    }

    public function ebookPreviewCss(int $fontSize, ?FontPairing $fontPairing = null): string
    {
        return $this->base->ebookPreviewCss($fontSize, $fontPairing ?? $this->defaultFontPairing())
            ."\n".$this->overrideCss();
    }

    public function epubCss(string $fontFaceCss, ?FontPairing $fontPairing = null): string
    {
        return $this->base->epubCss($fontFaceCss, $fontPairing ?? $this->defaultFontPairing())
            ."\n".$this->overrideCss();
    }

    public function sceneBreakCss(): string
    {
        return $this->base->sceneBreakCss();
    }

    public function dropCapCss(?FontPairing $fontPairing = null): string
    {
        return $this->base->dropCapCss($fontPairing ?? $this->defaultFontPairing());
    }

    public function epubDropCapCss(?FontPairing $fontPairing = null): string
    {
        return $this->base->epubDropCapCss($fontPairing ?? $this->defaultFontPairing());
    }

    public function chapterHeaderHtml(int $index, string $title, string $locale = 'en', bool $includeTitle = true): string
    {
        return $this->base->chapterHeaderHtml($index, $title, $locale, $includeTitle);
    }

    /**
     * CSS overriding the base template's typography from the stored settings.
     * Appended after the base CSS so plain selector-for-selector rules win.
     */
    private function overrideCss(): string
    {
        $typography = (array) ($this->settings['typography'] ?? []);
        $headings = (array) ($this->settings['headings'] ?? []);

        $rules = [];

        $bodyRules = [];
        if (isset($typography['line_height'])) {
            $bodyRules[] = 'line-height: '.(float) $typography['line_height'].';';
        }
        if (isset($typography['alignment'])) {
            $bodyRules[] = 'text-align: '.($typography['alignment'] === 'left' ? 'left' : 'justify').';';
        }
        if (($typography['hyphenation'] ?? true) === false) {
            $bodyRules[] = 'hyphens: none;';
            $bodyRules[] = '-webkit-hyphens: none;';
        }
        if ($bodyRules !== []) {
            $rules[] = "body {\n    ".implode("\n    ", $bodyRules)."\n}";
        }

        $paragraphRules = [];
        if (($typography['first_line_indent'] ?? true) === false) {
            $paragraphRules[] = 'text-indent: 0;';
        }
        if (! empty($typography['paragraph_spacing_em'])) {
            $paragraphRules[] = 'margin-bottom: '.(float) $typography['paragraph_spacing_em'].'em;';
        }
        if ($paragraphRules !== []) {
            $rules[] = "p {\n    ".implode("\n    ", $paragraphRules)."\n}";
        }

        if (isset($headings['heading_scale_em'])) {
            $rules[] = "h1 {\n    font-size: ".(float) $headings['heading_scale_em']."em;\n}";
        }
        if (isset($headings['heading_top_space_em'])) {
            $space = (float) $headings['heading_top_space_em'];
            // Preserve the base template's own bottom spacing — only the top
            // space is a Book Designer setting.
            $bottom = (float) ($this->base->designTokens()['labelBottomSpaceEm'] ?? 0.25);
            $rules[] = ".chapter-label {\n    margin: {$space}em 0 {$bottom}em;\n}";
        }

        return implode("\n", $rules);
    }
}
