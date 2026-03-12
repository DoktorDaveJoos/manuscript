<?php

namespace App\Services\Export;

use App\Enums\ExportFormat;
use App\Models\Book;
use App\Models\Chapter;
use App\Services\Export\Exporters\DocxExporter;
use App\Services\Export\Exporters\EpubExporter;
use App\Services\Export\Exporters\KdpExporter;
use App\Services\Export\Exporters\PdfExporter;
use App\Services\Export\Exporters\TxtExporter;
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
        $chapters = $this->resolveChapters($book, $options);

        $exportOptions = ExportOptions::fromArray($options);

        $exporter = $this->resolveExporter($format);
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
     * @return Collection<int, Chapter>
     */
    private function resolveChapters(Book $book, array $options): Collection
    {
        $query = $book->chapters()
            ->with(['scenes' => fn ($q) => $q->orderBy('sort_order'), 'act'])
            ->orderBy('reader_order');

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

    private function resolveExporter(ExportFormat $format): \App\Contracts\Exporter
    {
        $contentPreparer = new ContentPreparer;
        $fontService = new FontService;

        return match ($format) {
            ExportFormat::Docx => new DocxExporter($contentPreparer),
            ExportFormat::Txt => new TxtExporter($contentPreparer),
            ExportFormat::Epub => new EpubExporter($contentPreparer, $fontService),
            ExportFormat::Pdf => new PdfExporter($contentPreparer, $fontService),
            ExportFormat::Kdp => new KdpExporter(new EpubExporter($contentPreparer, $fontService)),
        };
    }
}
