<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateCoverRequest;
use App\Http\Requests\UpdateBookMatterChapterRequest;
use App\Http\Requests\UpdatePublishSettingsRequest;
use App\Http\Requests\UploadCoverImageRequest;
use App\Models\Book;
use App\Services\Export\CoverOptions;
use App\Services\Export\CoverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublishController extends Controller
{
    public function update(UpdatePublishSettingsRequest $request, Book $book): RedirectResponse
    {
        $book->update($request->validated());

        return back();
    }

    public function uploadCover(UploadCoverImageRequest $request, Book $book): RedirectResponse
    {
        if ($book->cover_image_path) {
            Storage::disk('local')->delete($book->cover_image_path);
        }

        $path = $request->file('cover_image')->store("covers/{$book->id}", 'local');

        // When the upload comes from the cover generator it carries the settings used
        // to build it, so the dialog can reopen pre-filled. A plain upload clears them.
        $coverSettings = $request->validated('cover_settings');

        $book->update([
            'cover_image_path' => $path,
            'cover_settings' => $coverSettings ?: null,
        ]);

        return back();
    }

    public function deleteCover(Book $book): RedirectResponse
    {
        if ($book->cover_image_path) {
            Storage::disk('local')->delete($book->cover_image_path);
            $book->update(['cover_image_path' => null, 'cover_settings' => null]);
        }

        return back();
    }

    /**
     * Render a live preview of the generated cover as a base64 PDF. Saving the cover
     * is handled by uploadCover (the client rasterizes the PDF to PNG first).
     */
    public function generateCover(GenerateCoverRequest $request, Book $book, CoverService $coverService): JsonResponse
    {
        $data = $request->validated();
        $face = $data['face'] ?? CoverService::FACE_FRONT;

        // The blurb is never sent by the client — the book's saved Klappentext is the
        // single source of truth, injected here so the back panel can render it.
        $options = CoverOptions::fromArray([...$data, 'blurb' => (string) $book->klappentext]);

        try {
            $pdfBytes = $coverService->generatePdfString($options, $face);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => 'Cover generation failed: '.$e->getMessage()], 500);
        }

        return response()->json(['pdf' => base64_encode($pdfBytes)]);
    }

    public function updateEpilogue(UpdateBookMatterChapterRequest $request, Book $book): RedirectResponse
    {
        $chapterId = $request->validated('chapter_id');

        DB::transaction(function () use ($book, $chapterId): void {
            $book->chapters()->update(['is_epilogue' => false]);

            if ($chapterId !== null) {
                $book->chapters()->where('id', $chapterId)->update([
                    'is_epilogue' => true,
                    'is_prologue' => false,
                ]);
            }
        });

        return back();
    }

    public function updatePrologue(UpdateBookMatterChapterRequest $request, Book $book): RedirectResponse
    {
        $chapterId = $request->validated('chapter_id');

        DB::transaction(function () use ($book, $chapterId): void {
            $book->chapters()->update(['is_prologue' => false]);

            if ($chapterId !== null) {
                $book->chapters()->where('id', $chapterId)->update([
                    'is_epilogue' => false,
                    'is_prologue' => true,
                ]);
            }
        });

        return back();
    }

    public function serveCover(Book $book): BinaryFileResponse
    {
        abort_unless($book->cover_image_path && Storage::disk('local')->exists($book->cover_image_path), 404);

        return response()->file(Storage::disk('local')->path($book->cover_image_path));
    }

    /**
     * Download the generated cover as a standalone, print-ready vector PDF (trim +
     * bleed), regenerated from the saved settings — print shops require the cover
     * as a separate file. Only available when the cover was built by the generator.
     */
    public function downloadCover(Book $book, CoverService $coverService): StreamedResponse
    {
        abort_unless(is_array($book->cover_settings) && filled($book->cover_settings['title'] ?? null), 404);

        // Full flattened jacket (back + spine + front) — what print shops require. The
        // back panel carries the book's Klappentext, merged in over the saved settings.
        $options = CoverOptions::fromArray([...$book->cover_settings, 'blurb' => (string) $book->klappentext]);
        $pdfBytes = $coverService->generatePdfString($options, CoverService::FACE_WRAPAROUND);
        $filename = Str::slug($book->title ?: 'cover').'-cover.pdf';

        return response()->streamDownload(
            fn () => print ($pdfBytes),
            $filename,
            [
                'Content-Type' => 'application/pdf',
                'Content-Length' => (string) strlen($pdfBytes),
            ],
        );
    }
}
