<?php

namespace App\Services\Export;

use App\Enums\ExportFormat;
use App\Enums\FontPairing;
use App\Enums\SceneBreakStyle;
use App\Enums\TrimSize;

final readonly class ExportOptions
{
    /**
     * @param  string[]  $frontMatter
     * @param  string[]  $backMatter
     */
    public function __construct(
        public bool $includeChapterTitles = true,
        public bool $includeActBreaks = false,
        public bool $includeTableOfContents = false,
        public bool $showPageNumbers = true,
        public ?TrimSize $trimSize = null,
        public int $fontSize = 11,
        public array $frontMatter = [],
        public array $backMatter = [],
        public string $copyrightText = '',
        public string $acknowledgmentText = '',
        public string $aboutAuthorText = '',
        public ?ExportFormat $previewFormat = null,
        public string $template = 'classic',
        public ?FontPairing $fontPairing = null,
        public ?SceneBreakStyle $sceneBreakStyle = null,
        public bool $dropCaps = false,
        public bool $includeCover = true,
        public string $dedicationText = '',
        public string $epigraphText = '',
        public string $epigraphAttribution = '',
        public string $alsoByText = '',
        public ?string $coverImagePath = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            includeChapterTitles: (bool) ($data['include_chapter_titles'] ?? true),
            includeActBreaks: (bool) ($data['include_act_breaks'] ?? false),
            includeTableOfContents: (bool) ($data['include_table_of_contents'] ?? false),
            showPageNumbers: (bool) ($data['show_page_numbers'] ?? true),
            trimSize: isset($data['trim_size']) ? TrimSize::from($data['trim_size']) : null,
            fontSize: (int) ($data['font_size'] ?? 11),
            frontMatter: (array) ($data['front_matter'] ?? []),
            backMatter: (array) ($data['back_matter'] ?? []),
            copyrightText: (string) ($data['copyright_text'] ?? ''),
            acknowledgmentText: (string) ($data['acknowledgment_text'] ?? ''),
            aboutAuthorText: (string) ($data['about_author_text'] ?? ''),
            previewFormat: isset($data['preview_format']) ? ExportFormat::from($data['preview_format']) : null,
            template: (string) ($data['template'] ?? 'classic'),
            fontPairing: isset($data['font_pairing']) ? FontPairing::from($data['font_pairing']) : null,
            sceneBreakStyle: isset($data['scene_break_style']) ? SceneBreakStyle::from($data['scene_break_style']) : null,
            dropCaps: (bool) ($data['drop_caps'] ?? false),
            includeCover: (bool) ($data['include_cover'] ?? true),
            dedicationText: (string) ($data['dedication_text'] ?? ''),
            epigraphText: (string) ($data['epigraph_text'] ?? ''),
            epigraphAttribution: (string) ($data['epigraph_attribution'] ?? ''),
            alsoByText: (string) ($data['also_by_text'] ?? ''),
            coverImagePath: isset($data['cover_image_path']) ? (string) $data['cover_image_path'] : null,
        );
    }
}
