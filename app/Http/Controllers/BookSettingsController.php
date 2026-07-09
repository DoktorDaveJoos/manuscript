<?php

namespace App\Http\Controllers;

use App\Contracts\ExportTemplate;
use App\Enums\BackMatterType;
use App\Enums\DocxLayout;
use App\Enums\ExportFormat;
use App\Enums\FrontMatterType;
use App\Enums\TrimSize;
use App\Http\Requests\ExportBookRequest;
use App\Http\Requests\UpdateBookGeneralSettingsRequest;
use App\Models\Book;
use App\Models\DesignTemplate;
use App\Services\Export\ContentPreparer;
use App\Services\Export\Exporters\PdfExporter;
use App\Services\Export\ExportOptions;
use App\Services\Export\ExportService;
use App\Services\Export\FontService;
use App\Services\Export\Templates\ClassicTemplate;
use App\Services\Export\Templates\ElegantTemplate;
use App\Services\Export\Templates\ModernTemplate;
use App\Services\WritingStyleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Native\Desktop\Dialog;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BookSettingsController extends Controller
{
    public function index(Book $book): RedirectResponse
    {
        return redirect()->route('books.settings.general', $book);
    }

    public function general(Book $book): Response
    {
        return Inertia::render('books/settings/general', [
            'book' => $book->only('id', 'title', 'subtitle', 'author', 'language', 'genre', 'secondary_genres'),
        ]);
    }

    public function updateGeneral(UpdateBookGeneralSettingsRequest $request, Book $book): RedirectResponse
    {
        $book->update($request->validated());

        return back();
    }

    public function writingStyle(Book $book): Response
    {
        return Inertia::render('books/settings/writing-style', [
            'book' => $book->only('id', 'title', 'writing_style_text', 'writing_style'),
            'writing_style_display' => $book->writing_style_display,
        ]);
    }

    public function updateWritingStyle(Request $request, Book $book): JsonResponse
    {
        $request->validate([
            'writing_style_text' => ['required', 'string', 'max:20000'],
        ]);

        $book->update([
            'writing_style_text' => $request->input('writing_style_text'),
        ]);

        return response()->json(['message' => __('Writing style updated.')]);
    }

    public function dismissWritingStylePrompt(Book $book): JsonResponse
    {
        $book->update(['writing_style_prompt_dismissed' => true]);

        return response()->json(['message' => __('Writing style prompt dismissed.')]);
    }

    public function regenerateWritingStyle(Book $book, WritingStyleService $service): JsonResponse
    {
        $sampleText = $book->writingStyleSample();

        if ($sampleText === null) {
            return response()->json(['message' => __('Not enough chapter content yet for style analysis.')], 422);
        }

        $result = $service->extract($sampleText, $book);

        $proseText = Book::formatWritingStyle($result);

        $book->update([
            'writing_style' => $result,
            'writing_style_text' => $proseText,
        ]);

        return response()->json([
            'message' => __('Writing style regenerated.'),
            'writing_style_text' => $proseText,
        ]);
    }

    public function proseRules(Book $book): Response
    {
        return Inertia::render('books/settings/prose-rules', [
            'book' => $book->only('id', 'title'),
            'rules' => $book->prosePassRules(),
        ]);
    }

    public function proofreading(Book $book): Response
    {
        return Inertia::render('books/settings/proofreading', [
            'book' => $book->only('id', 'title'),
            'config' => $book->proofreadingConfig(),
        ]);
    }

    public function updateProofreadingConfig(Request $request, Book $book): JsonResponse
    {
        $request->validate([
            'config' => ['required', 'array'],
            'config.spelling_enabled' => ['required', 'boolean'],
            'config.grammar_enabled' => ['required', 'boolean'],
            'config.grammar_checks' => ['required', 'array'],
        ]);

        $book->update([
            'proofreading_config' => $request->input('config'),
        ]);

        return response()->json(['message' => __('Proofreading settings updated.')]);
    }

    public function publishing(Book $book): Response
    {
        $book->load(['chapters' => fn ($q) => $q->orderBy('reader_order')]);

        return Inertia::render('books/settings/publishing', [
            'book' => $book->only(
                'id', 'title', 'author', 'language',
                'copyright_text', 'dedication_text', 'epigraph_text', 'epigraph_attribution',
                'acknowledgment_text', 'about_author_text', 'also_by_text', 'klappentext',
                'publisher_name', 'isbn',
            ),
            'chapters' => $book->chapters->map(fn ($ch) => [
                'id' => $ch->id,
                'title' => $ch->title,
                'is_epilogue' => $ch->is_epilogue,
                'is_prologue' => $ch->is_prologue,
            ]),
        ]);
    }

    public function cover(Book $book): Response
    {
        $bookData = $book->only('id', 'title', 'author', 'cover_settings', 'klappentext');

        // Cache-bust the served cover so a freshly generated/replaced image refreshes in-place.
        $bookData['cover_image_url'] = $book->cover_image_path
            ? route('books.publish.cover.serve', $book).'?v='.($book->updated_at?->timestamp ?? '')
            : null;

        // Seed the cover generator with the book's own metadata when it has no saved settings yet.
        $bookData['cover_genre'] = $book->genre?->label() ?? '';

        return Inertia::render('books/settings/cover', [
            'book' => $bookData,
            'trimSizes' => collect(TrimSize::cases())->map(fn (TrimSize $t) => [
                'value' => $t->value,
                'label' => $t->label(),
                'labelMetric' => $t->metricLabel(),
            ]),
        ]);
    }

    public function updateProsePassRules(Request $request, Book $book): JsonResponse
    {
        $request->validate([
            'rules' => ['required', 'array'],
            'rules.*.key' => ['required', 'string'],
            'rules.*.label' => ['required', 'string'],
            'rules.*.description' => ['required', 'string'],
            'rules.*.enabled' => ['required', 'boolean'],
        ]);

        $book->update([
            'prose_pass_rules' => $request->input('rules'),
        ]);

        return response()->json(['message' => __('Prose pass rules updated.')]);
    }

    /**
     * Persist the export page's UI selections so they survive navigation
     * and app restarts. Mirrors ExportBookRequest's rules — this is a saved
     * snapshot of the same payload, minus the per-run fields.
     */
    public function updateExportSettings(Request $request, Book $book): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array:format,template,cmyk,include_cover,front_matter,back_matter,excluded_chapter_ids,docx_layout'],
            'settings.format' => ['sometimes', Rule::enum(ExportFormat::class)],
            'settings.docx_layout' => ['sometimes', Rule::enum(DocxLayout::class)],
            'settings.template' => ['sometimes', 'string', 'regex:/^(classic|modern|elegant|romance|custom:\d+)$/'],
            'settings.cmyk' => ['sometimes', 'boolean'],
            'settings.include_cover' => ['sometimes', 'boolean'],
            'settings.front_matter' => ['sometimes', 'array'],
            'settings.front_matter.*' => ['string', Rule::enum(FrontMatterType::class)],
            'settings.back_matter' => ['sometimes', 'array'],
            'settings.back_matter.*' => ['string', Rule::enum(BackMatterType::class)],
            'settings.excluded_chapter_ids' => ['sometimes', 'array'],
            'settings.excluded_chapter_ids.*' => ['integer'],
        ]);

        $book->update(['export_settings' => $validated['settings']]);

        return response()->json(['message' => __('Export settings saved.')]);
    }

    public function export(Book $book): Response
    {
        $book->load('storylines', 'acts');

        $chapters = $book->chapters()
            ->select('id', 'book_id', 'storyline_id', 'act_id', 'title', 'reader_order', 'word_count', 'is_epilogue', 'is_prologue')
            ->with(['scenes' => fn ($q) => $q->orderBy('sort_order')->select('id', 'chapter_id', 'content')])
            ->orderBy('reader_order')
            ->get();

        return Inertia::render('books/export', [
            'book' => $book->only('id', 'title', 'author', 'cover_image_path'),
            'exportSettings' => $book->export_settings,
            'storylines' => $book->storylines->map(fn ($s) => $s->only('id', 'name', 'color', 'type')),
            'chapters' => $chapters->map(fn ($ch) => [
                ...$ch->only('id', 'storyline_id', 'act_id', 'title', 'reader_order', 'word_count', 'is_epilogue', 'is_prologue'),
                'content' => $ch->getContentWithSceneBreaks(),
            ]),
            'acts' => $book->acts->map(fn ($a) => $a->only('id', 'number', 'title')),
            'copyrightText' => $book->copyright_text ?? '',
            'acknowledgmentText' => $book->acknowledgment_text ?? '',
            'aboutAuthorText' => $book->about_author_text ?? '',
            'templates' => collect([new ClassicTemplate, new ModernTemplate, new ElegantTemplate])
                ->map(fn (ExportTemplate $t) => $this->exportTemplateDef($t, 'builtin'))
                ->concat(
                    DesignTemplate::query()
                        ->orderBy('name')
                        ->get()
                        ->map(fn (DesignTemplate $row) => $this->exportTemplateDef(
                            ExportService::resolveTemplate($row->slug()),
                            'custom',
                        )),
                )
                ->values(),
            'currentTemplate' => (string) ($book->export_settings['template'] ?? 'classic'),
        ]);
    }

    /**
     * Template row for the export page's picker, including the trim dimensions
     * the preview panel needs for its page aspect ratio.
     *
     * @return array<string, mixed>
     */
    private function exportTemplateDef(ExportTemplate $template, string $group): array
    {
        $pairing = $template->defaultFontPairing();
        $page = (array) ($template->designSettings()['page'] ?? []);

        if (($page['trim_size'] ?? null) === 'custom') {
            $trimWidth = (float) ($page['custom_width'] ?? 127);
            $trimHeight = (float) ($page['custom_height'] ?? 203.2);
        } else {
            $dims = (TrimSize::tryFrom((string) ($page['trim_size'] ?? '')) ?? TrimSize::Pocket)->dimensions();
            $trimWidth = $dims['width'];
            $trimHeight = $dims['height'];
        }

        return [
            'slug' => $template->slug(),
            'name' => $template->name(),
            'group' => $group,
            'headingFont' => $pairing->headingFont(),
            'bodyFont' => $pairing->bodyFont(),
            'trimWidth' => $trimWidth,
            'trimHeight' => $trimHeight,
        ];
    }

    public function doExport(ExportBookRequest $request, Book $book, ExportService $service): BinaryFileResponse|JsonResponse
    {
        $validated = $request->validated();
        $format = $validated['format'] ?? '';

        if ($format === 'pdf' && ! config('nativephp-internal.running')) {
            return response()->json(['error' => 'PDF export requires the desktop app'], 422);
        }

        if (config('nativephp-internal.running')) {
            return $this->exportWithNativeDialog($book, $validated, $service);
        }

        return $service->export($book, $validated);
    }

    private function exportWithNativeDialog(Book $book, array $validated, ExportService $service): JsonResponse
    {
        $format = ExportFormat::from($validated['format'] ?? 'docx');
        $downloadName = ExportService::downloadName($book, $format);
        $tempPath = null;

        try {
            // Generate the export file first so the user doesn't wait on both generation + dialog
            $tempPath = $service->exportToPath($book, $validated);

            $savePath = app(Dialog::class)
                ->title(__('Save Export'))
                ->defaultPath($downloadName)
                ->filter(strtoupper($format->extension()), [$format->extension()])
                ->button(__('Save'))
                ->asSheet()
                ->save();

            if (! $savePath) {
                return response()->json(['cancelled' => true]);
            }

            error_clear_last();
            if (! @copy($tempPath, $savePath)) {
                $copyError = error_get_last()['message'] ?? 'unknown error';

                return response()->json(['error' => $copyError], 500);
            }

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => $e->getMessage()], 500);
        } finally {
            if ($tempPath !== null) {
                @unlink($tempPath);
            }
        }
    }

    public function previewPdf(ExportBookRequest $request, Book $book): JsonResponse
    {
        $validated = $request->validated();
        $validated['preview_format'] = ExportFormat::from($validated['format'] ?? 'pdf')->value;
        $validated['include_cover'] = false;
        $chapters = ExportService::resolveChapters($book, $validated);
        ExportService::injectMatterText($validated, $book);
        ExportService::applyDesignTemplate($validated);
        $options = ExportOptions::fromArray($validated);

        $contentPreparer = new ContentPreparer;
        $fontService = new FontService;
        $template = ExportService::resolveTemplate($validated['template'] ?? 'classic');
        $exporter = new PdfExporter($contentPreparer, $fontService, $template);

        try {
            $pdfBytes = $exporter->generatePdfString($book, $chapters, $options);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => 'PDF generation failed: '.$e->getMessage()], 500);
        }

        return response()->json(['pdf' => base64_encode($pdfBytes)]);
    }
}
