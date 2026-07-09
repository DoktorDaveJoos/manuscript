<?php

namespace App\Services\Export;

use App\Contracts\Exporter;
use App\Contracts\ExportTemplate;
use App\Enums\ExportFormat;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\DesignTemplate;
use App\Services\Export\Exporters\DocxExporter;
use App\Services\Export\Exporters\EpubExporter;
use App\Services\Export\Exporters\KdpExporter;
use App\Services\Export\Exporters\PdfExporter;
use App\Services\Export\Exporters\TxtExporter;
use App\Services\Export\Templates\ClassicTemplate;
use App\Services\Export\Templates\CustomTemplate;
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
        $filePath = $this->exportToPath($book, $options);
        $downloadName = self::downloadName($book, $format);

        return response()->download($filePath, $downloadName)->deleteFileAfterSend();
    }

    /**
     * Export a book and return the temporary file path (caller is responsible for cleanup).
     *
     * @param  array<string, mixed>  $options
     */
    public function exportToPath(Book $book, array $options): string
    {
        $format = ExportFormat::from($options['format'] ?? 'docx');
        $chapters = self::resolveChapters($book, $options);

        self::injectMatterText($options, $book);
        self::applyDesignTemplate($options);

        $exportOptions = ExportOptions::fromArray($options);
        $template = self::resolveTemplate($exportOptions->template);

        $exporter = $this->resolveExporter($format, $template);

        return $exporter->export($book, $chapters, $exportOptions);
    }

    public static function downloadName(Book $book, ExportFormat $format): string
    {
        return Str::slug($book->title ?: 'export').'.'.$format->extension();
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
        $locale = $book->language ?? config('app.fallback_locale', 'en');

        if (in_array('copyright', $frontMatter)) {
            $options['copyright_text'] = $book->copyright_text
                ?? '© '.date('Y')." {$book->author}. ".__('All rights reserved.', [], $locale);
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

        // Exclude prologue chapters from the main body when prologue is in front matter
        $frontMatter = (array) ($options['front_matter'] ?? []);
        if (in_array('prologue', $frontMatter)) {
            $query->where('is_prologue', false);
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

    public static function resolvePrologueChapter(Book $book): ?Chapter
    {
        return $book->chapters()
            ->with(['scenes' => fn ($q) => $q->orderBy('sort_order')])
            ->where('is_prologue', true)
            ->first();
    }

    public static function resolveTemplate(string $slug): ExportTemplate
    {
        if (str_starts_with($slug, 'custom:')) {
            $row = DesignTemplate::find((int) substr($slug, strlen('custom:')));

            if ($row === null) {
                return new ClassicTemplate;
            }

            return new CustomTemplate(
                self::resolveTemplate($row->based_on),
                $row->settings ?? [],
                $row->name,
                $row->id,
            );
        }

        return match ($slug) {
            'modern' => new ModernTemplate,
            'elegant', 'romance' => new ElegantTemplate,
            default => new ClassicTemplate,
        };
    }

    /**
     * Fill the raw options array with the referenced template's page geometry
     * and typesetting settings. The template (built-in or Book Designer custom)
     * owns these values; per-run options only carry what the export page still
     * controls (format, matter, chapter selection) but explicitly passed
     * values keep precedence for programmatic callers.
     *
     * @param  array<string, mixed>  $options
     */
    public static function applyDesignTemplate(array &$options): void
    {
        $template = self::resolveTemplate((string) ($options['template'] ?? 'classic'));

        foreach (self::designDefaults($template) as $key => $value) {
            if (! array_key_exists($key, $options) || $options[$key] === null) {
                $options[$key] = $value;
            }
        }
    }

    /**
     * Map a template's Book Designer settings onto the export-option keys the
     * pipeline reads outside of CSS.
     *
     * @return array<string, mixed>
     */
    private static function designDefaults(ExportTemplate $template): array
    {
        $settings = $template->designSettings();
        $page = (array) ($settings['page'] ?? []);
        $typography = (array) ($settings['typography'] ?? []);
        $headings = (array) ($settings['headings'] ?? []);
        $structure = (array) ($settings['structure'] ?? []);

        $defaults = [
            'trim_size' => $page['trim_size'] ?? null,
            'custom_width' => $page['custom_width'] ?? null,
            'custom_height' => $page['custom_height'] ?? null,
            'bleed' => $page['bleed'] ?? null,
            'bleed_mode' => $page['bleed_mode'] ?? null,
            'margin_top' => $page['margin_top'] ?? null,
            'margin_bottom' => $page['margin_bottom'] ?? null,
            'margin_inner' => $page['margin_inner'] ?? null,
            'margin_outer' => $page['margin_outer'] ?? null,
            'font_pairing' => $typography['font_pairing'] ?? null,
            'font_size' => isset($typography['font_size']) ? (int) $typography['font_size'] : null,
            'hyphenation' => $typography['hyphenation'] ?? null,
            'chapter_heading' => $headings['chapter_heading'] ?? null,
            'scene_break_style' => $headings['scene_break_style'] ?? null,
            'drop_caps' => $headings['drop_caps'] ?? null,
            'show_page_numbers' => $structure['show_page_numbers'] ?? null,
            'include_act_breaks' => $structure['include_act_breaks'] ?? null,
        ];

        return array_filter($defaults, fn ($value) => $value !== null);
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
