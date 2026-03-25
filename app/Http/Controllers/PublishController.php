<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePublishSettingsRequest;
use App\Http\Requests\UploadCoverImageRequest;
use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PublishController extends Controller
{
    public function show(Book $book): Response
    {
        $book->load(['chapters' => fn ($q) => $q->orderBy('reader_order'), 'storylines']);

        return Inertia::render('books/publish', [
            'book' => $book->only(
                'id', 'title', 'author', 'language',
                'copyright_text', 'dedication_text', 'epigraph_text', 'epigraph_attribution',
                'acknowledgment_text', 'about_author_text', 'also_by_text',
                'publisher_name', 'isbn', 'cover_image_path',
            ),
            'chapters' => $book->chapters->map(fn ($ch) => [
                'id' => $ch->id,
                'title' => $ch->title,
                'is_epilogue' => $ch->is_epilogue,
            ]),
        ]);
    }

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

        $book->update(['cover_image_path' => $path]);

        return back();
    }

    public function deleteCover(Book $book): RedirectResponse
    {
        if ($book->cover_image_path) {
            Storage::disk('local')->delete($book->cover_image_path);
            $book->update(['cover_image_path' => null]);
        }

        return back();
    }

    public function updateEpilogue(Book $book): RedirectResponse
    {
        $chapterId = request('chapter_id');

        $book->chapters()->update(['is_epilogue' => false]);

        if ($chapterId) {
            $book->chapters()->where('id', $chapterId)->update(['is_epilogue' => true]);
        }

        return back();
    }
}
