<?php

namespace App\Http\Controllers;

use App\Enums\ExportFormat;
use App\Enums\FontPairing;
use App\Enums\SceneBreakStyle;
use App\Enums\TrimSize;
use App\Http\Requests\ExportBookRequest;
use App\Models\Book;
use App\Services\Export\ContentPreparer;
use App\Services\Export\Exporters\PdfExporter;
use App\Services\Export\ExportOptions;
use App\Services\Export\ExportService;
use App\Services\Export\FontService;
use App\Services\Export\Templates\ClassicTemplate;
use App\Services\Export\Templates\ElegantTemplate;
use App\Services\Export\Templates\ModernTemplate;
use App\Services\FreeTierLimits;
use App\Services\WritingStyleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Native\Desktop\Dialog;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BookSettingsController extends Controller
{
    public function writingStyle(Book $book): Response
    {
        return Inertia::render('settings/book/writing-style', [
            'book' => $book->only('id', 'title', 'writing_style_text', 'writing_style'),
            'writing_style_display' => $book->writing_style_display,
        ]);
    }

    public function updateWritingStyle(Request $request, Book $book): JsonResponse
    {
        $request->validate([
            'writing_style_text' => ['required', 'string', 'max:5000'],
        ]);

        $book->update([
            'writing_style_text' => $request->input('writing_style_text'),
        ]);

        return response()->json(['message' => __('Writing style updated.')]);
    }

    public function regenerateWritingStyle(Book $book, WritingStyleService $service): JsonResponse
    {
        $chapters = $book->chapters()
            ->whereHas('versions', fn ($q) => $q->where('is_current', true))
            ->with(['versions' => fn ($q) => $q->where('is_current', true)])
            ->limit(5)
            ->get();

        $sampleText = $chapters
            ->map(fn ($ch) => $ch->versions->first()?->content ?? '')
            ->filter()
            ->implode("\n\n");

        if (blank($sampleText)) {
            return response()->json(['message' => __('No chapter content available for style analysis.')], 422);
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

    public function prosePassRules(Book $book): Response
    {
        return Inertia::render('settings/book/prose-pass-rules', [
            'book' => $book->only('id', 'title'),
            'rules' => $book->prose_pass_rules ?? Book::defaultProsePassRules(),
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

    public function export(Book $book): Response
    {
        $book->load('storylines', 'acts');

        $chapters = $book->chapters()
            ->select('id', 'book_id', 'storyline_id', 'act_id', 'title', 'reader_order', 'word_count', 'is_epilogue')
            ->with(['scenes' => fn ($q) => $q->orderBy('sort_order')->select('id', 'chapter_id', 'content')])
            ->orderBy('reader_order')
            ->get();

        return Inertia::render('books/export', [
            'book' => $book->only('id', 'title', 'author', 'cover_image_path'),
            'storylines' => $book->storylines->map(fn ($s) => $s->only('id', 'name', 'color', 'type')),
            'chapters' => $chapters->map(fn ($ch) => [
                ...$ch->only('id', 'storyline_id', 'act_id', 'title', 'reader_order', 'word_count', 'is_epilogue'),
                'content' => $ch->getContentWithSceneBreaks(),
            ]),
            'trimSizes' => collect(TrimSize::cases())->map(function ($t) {
                $dims = $t->dimensions();

                return [
                    'value' => $t->value,
                    'label' => $t->label(),
                    'width' => $dims['width'],
                    'height' => $dims['height'],
                ];
            }),
            'acts' => $book->acts->map(fn ($a) => $a->only('id', 'number', 'title')),
            'copyrightText' => $book->copyright_text ?? '',
            'acknowledgmentText' => $book->acknowledgment_text ?? '',
            'aboutAuthorText' => $book->about_author_text ?? '',
            'templates' => collect([new ClassicTemplate, new ModernTemplate, new ElegantTemplate])
                ->map(function ($t) {
                    $pairing = $t->defaultFontPairing();

                    return [
                        'slug' => $t->slug(),
                        'name' => $t->name(),
                        'pack' => 'Basic',
                        'defaultFontPairing' => $pairing->value,
                        'defaultSceneBreakStyle' => $t->defaultSceneBreakStyle()->value,
                        'defaultDropCaps' => $t->defaultDropCaps(),
                        'headingFont' => $pairing->headingFont(),
                        'bodyFont' => $pairing->bodyFont(),
                    ];
                }),
            'fontPairings' => collect(FontPairing::cases())->map(fn ($fp) => [
                'value' => $fp->value,
                'label' => $fp->label(),
                'headingFont' => $fp->headingFont(),
                'bodyFont' => $fp->bodyFont(),
            ]),
            'sceneBreakStyles' => collect(SceneBreakStyle::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    public function doExport(ExportBookRequest $request, Book $book, ExportService $service): BinaryFileResponse|JsonResponse
    {
        $validated = $request->validated();
        $format = $validated['format'] ?? '';

        if (! FreeTierLimits::canExportFormat($format)) {
            return response()->json([
                'message' => __('Upgrade to Manuscript Pro to export as :format.', ['format' => strtoupper($format)]),
            ], 403);
        }

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
        if (! FreeTierLimits::canExportFormat('pdf')) {
            return response()->json(['message' => __('PDF preview requires Manuscript Pro.')], 403);
        }

        $validated = $request->validated();
        $validated['preview_format'] = ExportFormat::from($validated['format'] ?? 'pdf')->value;
        $validated['include_cover'] = false;
        $chapters = ExportService::resolveChapters($book, $validated);
        ExportService::injectMatterText($validated, $book);
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
