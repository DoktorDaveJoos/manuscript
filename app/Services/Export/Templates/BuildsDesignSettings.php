<?php

namespace App\Services\Export\Templates;

use App\Enums\ChapterHeading;
use App\Enums\TrimSize;

/**
 * Default designSettings() implementation for the built-in templates: the
 * template's own defaults expressed as the Book Designer's settings shape.
 */
trait BuildsDesignSettings
{
    /**
     * @return array<string, mixed>
     */
    public function designSettings(): array
    {
        $trimSize = TrimSize::Pocket;
        $margins = $trimSize->margins();
        $tokens = $this->designTokens();

        return [
            'page' => [
                'trim_size' => $trimSize->value,
                'custom_width' => null,
                'custom_height' => null,
                'bleed' => 0,
                'bleed_mode' => 'all',
                'margin_top' => $margins['top'],
                'margin_bottom' => $margins['bottom'],
                'margin_inner' => $margins['gutter'],
                'margin_outer' => $margins['outer'],
            ],
            'typography' => [
                'font_pairing' => $this->defaultFontPairing()->value,
                'font_size' => 11,
                'line_height' => (float) ($tokens['pdfLineHeight'] ?? 1.35),
                'alignment' => 'justify',
                'hyphenation' => true,
                'first_line_indent' => true,
                'paragraph_spacing_em' => 0,
            ],
            'headings' => [
                'chapter_heading' => ChapterHeading::Full->value,
                'heading_scale_em' => (float) ($tokens['titleSizeEm'] ?? 1.8),
                'heading_top_space_em' => (float) ($tokens['labelTopSpaceEm'] ?? 9.0),
                'drop_caps' => $this->defaultDropCaps(),
                'scene_break_style' => $this->defaultSceneBreakStyle()->value,
            ],
            'structure' => [
                'show_page_numbers' => true,
                'include_act_breaks' => false,
            ],
        ];
    }
}
