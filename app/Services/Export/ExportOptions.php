<?php

namespace App\Services\Export;

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
        public ?int $fontSize = null,
        public array $frontMatter = [],
        public array $backMatter = [],
        public string $dedicationText = '',
        public string $acknowledgmentText = '',
        public string $aboutAuthorText = '',
        public string $alsoByText = '',
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
            frontMatter: (array) ($data['front_matter'] ?? []),
            backMatter: (array) ($data['back_matter'] ?? []),
            dedicationText: (string) ($data['dedication_text'] ?? ''),
            acknowledgmentText: (string) ($data['acknowledgment_text'] ?? ''),
            aboutAuthorText: (string) ($data['about_author_text'] ?? ''),
            alsoByText: (string) ($data['also_by_text'] ?? ''),
        );
    }
}
