<?php

namespace App\Services\Export;

use App\Contracts\Exporter;
use App\Contracts\ExportTemplate;
use App\Enums\ExportFormat;
use App\Models\Book;
use App\Models\Chapter;
use App\Services\Export\Exporters\DocxExporter;
use App\Services\Export\Exporters\EpubExporter;
use App\Services\Export\Exporters\KdpExporter;
use App\Services\Export\Exporters\PdfExporter;
use App\Services\Export\Exporters\TxtExporter;
use App\Services\Export\Templates\ClassicTemplate;
use App\Services\Export\Templates\ElegantTemplate;
use App\Services\Export\Templates\ModernTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportService
{
    /**
     * Export a book as a downloadable file.
     *
     * @param  array<string, mixed>  $options
     */
    public function export(Book $book, array $options): BinaryFileResponse
    {
        $format = ExportFormat::from($options['format'] ?? 'docx');
        $chapters = self::resolveChapters($book, $options);

        self::injectMatterText($options, $book);

        $exportOptions = ExportOptions::fromArray($options);
        $template = self::resolveTemplate($exportOptions->template);

        $exporter = $this->resolveExporter($format, $template);
        $filePath = $exporter->export($book, $chapters, $exportOptions);

        $downloadName = Str::slug($book->title ?: 'export').'.'.$format->extension();

        return response()->download($filePath, $downloadName)->deleteFileAfterSend();
    }

    /**
     * Generate a safe temporary file path for export output.
     */
    public static function tempPath(string $extension): string
    {
        return storage_path('app/export-'.Str::uuid().'.'.$extension);
    }

    /**
     * Inject per-book content into options when front/back matter is requested.
     *
     * @param  array<string, mixed>  $options
     */
    public static function injectMatterText(array &$options, Book $book): void
    {
        $frontMatter = (array) ($options['front_matter'] ?? []);
        $backMatter = (array) ($options['back_matter'] ?? []);

        if (in_array('copyright', $frontMatter)) {
            $options['copyright_text'] = $book->copyright_text ?? '© '.date('Y')." {$book->author}. All rights reserved.";
        }
        if (in_array('dedication', $frontMatter)) {
            $options['dedication_text'] = $book->dedication_text ?? '';
        }
        if (in_array('epigraph', $frontMatter)) {
            $options['epigraph_text'] = $book->epigraph_text ?? '';
            $options['epigraph_attribution'] = $book->epigraph_attribution ?? '';
        }
        if (in_array('acknowledgments', $backMatter)) {
            $options['acknowledgment_text'] = $book->acknowledgment_text ?? '';
        }
        if (in_array('about-author', $backMatter)) {
            $options['about_author_text'] = $book->about_author_text ?? '';
        }
        if (in_array('also-by', $backMatter)) {
            $options['also_by_text'] = $book->also_by_text ?? '';
        }

        $options['cover_image_path'] = $book->cover_image_path;
    }

    /**
     * @return Collection<int, Chapter>
     */
    public static function resolveChapters(Book $book, array $options): Collection
    {
        $query = $book->chapters()
            ->with(['scenes' => fn ($q) => $q->orderBy('sort_order'), 'act'])
            ->orderBy('reader_order');

        // Exclude epilogue chapters from the main body when epilogue is in back matter
        $backMatter = (array) ($options['back_matter'] ?? []);
        if (in_array('epilogue', $backMatter)) {
            $query->where('is_epilogue', false);
        }

        // chapter_ids takes priority — load in exact order given
        if (! empty($options['chapter_ids'])) {
            $ids = $options['chapter_ids'];
            $query->whereIn('id', $ids);

            return $query->get()->sortBy(function (Chapter $chapter) use ($ids) {
                return array_search($chapter->id, $ids);
            })->values();
        }

        $scope = $options['scope'] ?? 'full';

        if ($scope === 'chapter' && isset($options['chapter_id'])) {
            $query->where('id', $options['chapter_id']);
        } elseif ($scope === 'storyline' && isset($options['storyline_id'])) {
            $query->where('storyline_id', $options['storyline_id']);
        }

        return $query->get();
    }

    public static function resolveEpilogueChapter(Book $book): ?Chapter
    {
        return $book->chapters()
            ->with(['scenes' => fn ($q) => $q->orderBy('sort_order')])
            ->where('is_epilogue', true)
            ->first();
    }

    public static function resolveTemplate(string $slug): ExportTemplate
    {
        return match ($slug) {
            'modern' => new ModernTemplate,
            'elegant', 'romance' => new ElegantTemplate,
            default => new ClassicTemplate,
        };
    }

    private function resolveExporter(ExportFormat $format, ExportTemplate $template): Exporter
    {
        $contentPreparer = new ContentPreparer;
        $fontService = new FontService;

        return match ($format) {
            ExportFormat::Docx => new DocxExporter($contentPreparer, $template),
            ExportFormat::Txt => new TxtExporter($contentPreparer),
            ExportFormat::Epub => new EpubExporter($contentPreparer, $fontService, $template),
            ExportFormat::Pdf => new PdfExporter($contentPreparer, $fontService, $template),
            ExportFormat::Kdp => new KdpExporter(new EpubExporter($contentPreparer, $fontService, $template)),
        };
    }
}
