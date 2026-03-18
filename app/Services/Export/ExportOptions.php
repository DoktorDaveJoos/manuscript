<?php

namespace App\Services\Export;

use App\Enums\TrimSize;

final readonly class ExportOptions
{
    public function __construct(
        public bool $includeChapterTitles = true,
        public bool $includeActBreaks = false,
        public bool $includeTableOfContents = false,
        public bool $showPageNumbers = true,
        public ?TrimSize $trimSize = null,
        public ?int $fontSize = null,
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
            fontSize: isset($data['font_size']) ? (int) $data['font_size'] : null,
        );
    }
}
