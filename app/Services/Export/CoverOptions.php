<?php

namespace App\Services\Export;

use App\Enums\TrimSize;

final readonly class CoverOptions
{
    public function __construct(
        public string $title,
        public string $subtitle = '',
        public string $author = '',
        public ?TrimSize $trimSize = null,
        public float $bleed = 3.0,
        public float $safety = 5.0,
        public float $spineWidth = 0.0,
        public string $blurb = '',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: trim((string) ($data['title'] ?? '')),
            subtitle: trim((string) ($data['subtitle'] ?? '')),
            author: trim((string) ($data['author'] ?? '')),
            trimSize: isset($data['trim_size']) ? TrimSize::tryFrom((string) $data['trim_size']) : null,
            bleed: isset($data['bleed']) ? (float) $data['bleed'] : 3.0,
            safety: isset($data['safety']) ? (float) $data['safety'] : 5.0,
            spineWidth: isset($data['spine_width']) ? max(0.0, (float) $data['spine_width']) : 0.0,
            blurb: trim((string) ($data['blurb'] ?? '')),
        );
    }

    /**
     * Resolve the trim size, defaulting to the 13 × 19 cm paperback the cover
     * generator was specced against.
     */
    public function trim(): TrimSize
    {
        return $this->trimSize ?? TrimSize::Novel13x19;
    }

    /**
     * Front-panel delivery format in millimetres — one trim plus bleed on every
     * edge. This is the file readers see (thumbnail, e-book cover).
     *
     * @return array{width: float, height: float}
     */
    public function dataDimensions(): array
    {
        $trim = $this->trim()->dimensions();

        return [
            'width' => $trim['width'] + 2 * $this->bleed,
            'height' => $trim['height'] + 2 * $this->bleed,
        ];
    }

    /**
     * Full flattened jacket in millimetres — back + spine + front, plus bleed on
     * the outer edges. This is the print-shop "Datenformat": for a 130×190 trim
     * with a 3.5 mm spine and 3 mm bleed that is 269.5 × 196 mm (FlyerAlarm spec).
     *
     * @return array{width: float, height: float}
     */
    public function wraparoundDimensions(): array
    {
        $trim = $this->trim()->dimensions();

        return [
            'width' => 2 * $trim['width'] + $this->spineWidth + 2 * $this->bleed,
            'height' => $trim['height'] + 2 * $this->bleed,
        ];
    }

    /**
     * Distance from the data-format edge to the safe text area: bleed keeps text
     * off the cut line, safety keeps it clear of the trim edge.
     */
    public function contentMargin(): float
    {
        return $this->bleed + $this->safety;
    }

    /**
     * Persistable settings so the generator dialog reopens with prior values.
     *
     * @return array{title: string, subtitle: string, author: string, trim_size: string, bleed: float, safety: float, spine_width: float}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'author' => $this->author,
            'trim_size' => $this->trim()->value,
            'bleed' => $this->bleed,
            'safety' => $this->safety,
            'spine_width' => $this->spineWidth,
        ];
    }
}
