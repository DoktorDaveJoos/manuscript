<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Native\Desktop\Facades\Window;

class ChapterDiffController extends Controller
{
    public function show(Book $book, Chapter $chapter, ChapterVersion $version): InertiaResponse
    {
        abort_unless($chapter->book_id === $book->id, 404);
        abort_unless($version->chapter_id === $chapter->id, 404);

        $previous = $chapter->versions()
            ->where('version_number', '<', $version->version_number)
            ->orderByDesc('version_number')
            ->first();

        return Inertia::render('chapters/diff', [
            'book' => $book->only(['id', 'title']),
            'chapter' => $chapter->only(['id', 'title']),
            'currentVersion' => $previous ? $this->serializeVersion($previous) : null,
            'pendingVersion' => $this->serializeVersion($version),
        ]);
    }

    public function openWindow(Book $book, Chapter $chapter, ChapterVersion $version): JsonResponse
    {
        abort_unless($chapter->book_id === $book->id, 404);
        abort_unless($version->chapter_id === $chapter->id, 404);

        if (! config('nativephp-internal.running')) {
            return response()->json(['error' => __('Requires the desktop app.')], 422);
        }

        $windowId = $this->windowId($chapter);

        // Window::open with an existing id focuses the existing window in NativePHP,
        // so re-clicking "Review" doesn't spawn duplicates.
        $pending = Window::open($windowId)
            ->title(__('Review changes — :chapter', ['chapter' => $chapter->title]))
            ->backgroundColor('#161616')
            ->width(1280)
            ->height(820)
            ->minWidth(960)
            ->minHeight(600)
            ->url(route('chapters.diff.show', [
                'book' => $book->id,
                'chapter' => $chapter->id,
                'version' => $version->id,
            ]));

        unset($pending);

        return response()->json(['window' => $windowId]);
    }

    public function closeWindow(Chapter $chapter): Response
    {
        if (config('nativephp-internal.running')) {
            Window::close($this->windowId($chapter));
        }

        return response()->noContent();
    }

    private function windowId(Chapter $chapter): string
    {
        return "diff-chapter-{$chapter->id}";
    }

    /**
     * @return array{id: int, version_number: int, content: ?string, source: string, status: string}
     */
    private function serializeVersion(ChapterVersion $version): array
    {
        return [
            'id' => $version->id,
            'version_number' => $version->version_number,
            'content' => $version->content,
            'source' => $version->source->value,
            'status' => $version->status->value,
        ];
    }
}
