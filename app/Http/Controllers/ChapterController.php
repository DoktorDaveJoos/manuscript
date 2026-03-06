<?php

namespace App\Http\Controllers;

use App\Enums\ChapterStatus;
use App\Enums\VersionSource;
use App\Http\Requests\StoreChapterRequest;
use App\Http\Requests\UpdateChapterContentRequest;
use App\Http\Requests\UpdateChapterTitleRequest;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ChapterController extends Controller
{
    public function editor(Book $book): RedirectResponse|Response
    {
        $firstChapter = $book->chapters()
            ->orderBy('reader_order')
            ->first();

        if (! $firstChapter) {
            $book->load('storylines:id,book_id,name');

            return Inertia::render('chapters/empty', [
                'book' => $book,
            ]);
        }

        return redirect()->route('chapters.show', [$book, $firstChapter]);
    }

    public function store(StoreChapterRequest $request, Book $book): RedirectResponse
    {
        $book->storylines()->findOrFail($request->validated('storyline_id'));

        $chapter = DB::transaction(function () use ($request, $book) {
            $nextOrder = ($book->chapters()->max('reader_order') ?? -1) + 1;

            $chapter = $book->chapters()->create([
                'storyline_id' => $request->validated('storyline_id'),
                'title' => $request->validated('title'),
                'reader_order' => $nextOrder,
                'status' => ChapterStatus::Draft,
                'word_count' => 0,
            ]);

            $chapter->versions()->create([
                'version_number' => 1,
                'content' => '',
                'source' => VersionSource::Original,
                'is_current' => true,
            ]);

            return $chapter;
        });

        return redirect()->route('chapters.show', [$book, $chapter]);
    }

    public function show(Book $book, Chapter $chapter): Response
    {
        $book->load([
            'storylines' => fn ($q) => $q->orderBy('sort_order'),
            'storylines.chapters' => fn ($q) => $q
                ->select('id', 'book_id', 'storyline_id', 'title', 'reader_order', 'status', 'word_count')
                ->orderBy('reader_order'),
        ]);

        $chapter->load([
            'currentVersion:id,chapter_id,version_number,content,source,is_current',
            'storyline:id,name,timeline_label',
            'povCharacter:id,name',
            'characters' => fn ($q) => $q->select('characters.id', 'characters.name'),
        ]);

        return Inertia::render('chapters/show', [
            'book' => $book,
            'chapter' => $chapter,
            'versionCount' => $chapter->versions()->count(),
        ]);
    }

    public function updateTitle(UpdateChapterTitleRequest $request, Book $book, Chapter $chapter): JsonResponse
    {
        $chapter->update(['title' => $request->validated('title')]);

        return response()->json([
            'title' => $chapter->title,
            'saved_at' => now()->toISOString(),
        ]);
    }

    public function updateContent(UpdateChapterContentRequest $request, Book $book, Chapter $chapter): JsonResponse
    {
        $version = $chapter->currentVersion;

        if (! $version) {
            return response()->json(['error' => 'No current version found'], 404);
        }

        $version->update([
            'content' => $request->validated('content'),
        ]);

        $wordCount = str_word_count(strip_tags($request->validated('content')));
        $chapter->update(['word_count' => $wordCount]);

        return response()->json([
            'word_count' => $wordCount,
            'saved_at' => now()->toISOString(),
        ]);
    }

    public function versions(Book $book, Chapter $chapter): JsonResponse
    {
        $versions = $chapter->versions()
            ->select('id', 'chapter_id', 'version_number', 'source', 'change_summary', 'is_current', 'created_at')
            ->orderByDesc('version_number')
            ->get();

        return response()->json($versions);
    }

    public function restoreVersion(Book $book, Chapter $chapter, ChapterVersion $version): RedirectResponse
    {
        DB::transaction(function () use ($chapter, $version) {
            $chapter->versions()->update(['is_current' => false]);

            $latestVersionNumber = $chapter->versions()->max('version_number');

            $chapter->versions()->create([
                'version_number' => $latestVersionNumber + 1,
                'content' => $version->content,
                'source' => 'manual_edit',
                'change_summary' => "Restored from version {$version->version_number}",
                'is_current' => true,
            ]);
        });

        return redirect()->back();
    }
}
