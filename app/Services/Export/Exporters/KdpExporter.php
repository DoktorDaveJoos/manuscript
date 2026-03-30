<?php

namespace App\Services\Export\Exporters;

use App\Contracts\Exporter;
use App\Models\Book;
use App\Services\Export\ExportOptions;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class KdpExporter implements Exporter
{
    public function __construct(
        private EpubExporter $epubExporter,
    ) {}

    public function export(Book $book, Collection $chapters, ExportOptions $options): string
    {
        $this->validateMetadata($book);

        // KDP enforces stricter requirements
        $kdpOptions = new ExportOptions(
            includeChapterTitles: true,
            includeActBreaks: $options->includeActBreaks,
            includeTableOfContents: true,
            trimSize: $options->trimSize,
            fontSize: $options->fontSize,
            frontMatter: $options->frontMatter,
            backMatter: $options->backMatter,
            copyrightText: $options->copyrightText,
            acknowledgmentText: $options->acknowledgmentText,
            aboutAuthorText: $options->aboutAuthorText,
            template: $options->template,
            fontPairing: $options->fontPairing,
            sceneBreakStyle: $options->sceneBreakStyle,
            dropCaps: $options->dropCaps,
            includeCover: $options->includeCover,
            dedicationText: $options->dedicationText,
            epigraphText: $options->epigraphText,
            epigraphAttribution: $options->epigraphAttribution,
            alsoByText: $options->alsoByText,
            coverImagePath: $options->coverImagePath,
        );

        return $this->epubExporter->export($book, $chapters, $kdpOptions);
    }

    private function validateMetadata(Book $book): void
    {
        $missing = [];

        if (blank($book->title)) {
            $missing[] = 'title';
        }

        if (blank($book->author)) {
            $missing[] = 'author';
        }

        if (blank($book->language)) {
            $missing[] = 'language';
        }

        if ($missing !== []) {
            throw new InvalidArgumentException(
                'KDP export requires: '.implode(', ', $missing).'. Please update your book settings.',
            );
        }

        if (! $book->cover_image_path) {
            logger()->info("KDP export for book {$book->id} missing cover image");
        }
    }
}
