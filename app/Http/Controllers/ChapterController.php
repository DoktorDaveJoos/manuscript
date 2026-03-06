<?php

namespace App\Http\Controllers;

use App\Enums\ChapterStatus;
use App\Enums\VersionSource;
use App\Http\Requests\ReorderChaptersRequest;
use App\Http\Requests\SplitChapterRequest;
use App\Http\Requests\StoreChapterRequest;
use App\Http\Requests\UpdateChapterContentRequest;
use App\Http\Requests\UpdateChapterNotesRequest;
use App\Http\Requests\UpdateChapterStatusRequest;
use App\Http\Requests\UpdateChapterTitleRequest;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\WritingSession;
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

            $chapter->scenes()->create([
                'title' => 'Scene 1',
                'content' => '',
                'sort_order' => 0,
                'word_count' => 0,
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
            'storylines.chapters.scenes' => fn ($q) => $q
                ->select('id', 'chapter_id', 'title', 'sort_order', 'word_count')
                ->orderBy('sort_order'),
        ]);

        $chapter->load([
            'currentVersion:id,chapter_id,version_number,content,source,is_current',
            'scenes' => fn ($q) => $q->orderBy('sort_order'),
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

        $previousWordCount = $chapter->word_count;
        $wordCount = str_word_count(strip_tags($request->validated('content')));
        $chapter->update(['word_count' => $wordCount]);

        $delta = $wordCount - $previousWordCount;
        if ($delta > 0) {
            $session = WritingSession::updateOrCreate(
                ['book_id' => $book->id, 'date' => now()->toDateString()],
                [],
            );

            $session->increment('words_written', $delta);
            $session->refresh();

            if ($book->daily_word_count_goal && $session->words_written >= $book->daily_word_count_goal) {
                $session->update(['goal_met' => true]);
            }
        }

        return response()->json([
            'word_count' => $wordCount,
            'saved_at' => now()->toISOString(),
        ]);
    }

    public function updateNotes(UpdateChapterNotesRequest $request, Book $book, Chapter $chapter): JsonResponse
    {
        $chapter->update(['notes' => $request->validated('notes')]);

        return response()->json([
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

    public function split(SplitChapterRequest $request, Book $book, Chapter $chapter): JsonResponse
    {
        $newChapter = DB::transaction(function () use ($request, $book, $chapter) {
            $book->chapters()
                ->where('reader_order', '>', $chapter->reader_order)
                ->increment('reader_order');

            $content = $request->validated('initial_content') ?? '';
            $wordCount = str_word_count(strip_tags($content));

            $newChapter = $book->chapters()->create([
                'storyline_id' => $chapter->storyline_id,
                'title' => $request->validated('title'),
                'reader_order' => $chapter->reader_order + 1,
                'status' => ChapterStatus::Draft,
                'word_count' => $wordCount,
            ]);

            $newChapter->versions()->create([
                'version_number' => 1,
                'content' => $content,
                'source' => VersionSource::Original,
                'is_current' => true,
            ]);

            $newChapter->scenes()->create([
                'title' => 'Scene 1',
                'content' => $content,
                'sort_order' => 0,
                'word_count' => $wordCount,
            ]);

            return $newChapter;
        });

        return response()->json([
            'chapter_id' => $newChapter->id,
            'url' => route('chapters.show', [$book, $newChapter]),
        ]);
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

            // Replace all scenes with single scene from restored content
            $chapter->scenes()->delete();
            $wordCount = str_word_count(strip_tags($version->content ?? ''));
            $chapter->scenes()->create([
                'title' => 'Scene 1',
                'content' => $version->content,
                'sort_order' => 0,
                'word_count' => $wordCount,
            ]);
            $chapter->update(['word_count' => $wordCount]);
        });

        return redirect()->back();
    }

    public function destroy(Book $book, Chapter $chapter): RedirectResponse
    {
        $deletedOrder = $chapter->reader_order;
        $chapter->delete();

        $book->chapters()
            ->where('reader_order', '>', $deletedOrder)
            ->decrement('reader_order');

        $nextChapter = $book->chapters()
            ->orderBy('reader_order')
            ->first();

        if ($nextChapter) {
            return redirect()->route('chapters.show', [$book, $nextChapter]);
        }

        return redirect()->route('books.editor', $book);
    }

    public function updateStatus(UpdateChapterStatusRequest $request, Book $book, Chapter $chapter): JsonResponse
    {
        $chapter->update(['status' => $request->validated('status')]);

        return response()->json([
            'status' => $chapter->status->value,
        ]);
    }

    public function reorder(ReorderChaptersRequest $request, Book $book): JsonResponse
    {
        $order = $request->validated('order');

        if (count($order) > 0) {
            $orderCase = '';
            $storylineCase = '';
            $ids = [];

            foreach ($order as $index => $item) {
                $id = (int) $item['id'];
                $storylineId = (int) $item['storyline_id'];
                $ids[] = $id;
                $orderCase .= "WHEN {$id} THEN {$index} ";
                $storylineCase .= "WHEN {$id} THEN {$storylineId} ";
            }

            $idList = implode(',', $ids);

            DB::statement(
                "UPDATE chapters SET reader_order = CASE id {$orderCase}END, storyline_id = CASE id {$storylineCase}END WHERE id IN ({$idList})"
            );
        }

        return response()->json(['success' => true]);
    }
}
