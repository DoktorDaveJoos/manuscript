<?php

namespace App\Http\Controllers;

use App\Enums\ChapterStatus;
use App\Enums\VersionSource;
use App\Enums\VersionStatus;
use App\Http\Requests\AssignChapterActRequest;
use App\Http\Requests\CreateSnapshotRequest;
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
use App\Support\WordCount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ChapterController extends Controller
{
    public function editor(Request $request, Book $book): RedirectResponse|Response
    {
        $firstChapter = $book->chapters()
            ->orderBy('reader_order')
            ->first();

        if (! $firstChapter && ! $request->query('panes')) {
            $book->load('storylines:id,book_id,name');

            return Inertia::render('chapters/empty', [
                'book' => $book,
            ]);
        }

        $book->load([
            'storylines' => fn ($q) => $q->orderBy('sort_order'),
            'storylines.chapters' => fn ($q) => $q
                ->select('id', 'book_id', 'storyline_id', 'title', 'reader_order', 'status', 'word_count')
                ->orderBy('reader_order'),
            'storylines.chapters.scenes' => fn ($q) => $q
                ->select('id', 'chapter_id', 'title', 'sort_order', 'word_count')
                ->orderBy('sort_order'),
        ]);

        return Inertia::render('chapters/editor', [
            'book' => $book,
            'initialPanes' => $request->query('panes'),
            'fallbackChapterId' => $firstChapter?->id,
        ]);
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

            if ($beatId = $request->validated('beat_id')) {
                $chapter->beats()->attach($beatId);
            }

            return $chapter;
        });

        return redirect()->route('chapters.show', [$book, $chapter]);
    }

    public function show(Book $book, Chapter $chapter): RedirectResponse
    {
        return redirect()->route('books.editor', ['book' => $book, 'panes' => $chapter->id]);
    }

    public function showJson(Book $book, Chapter $chapter): JsonResponse
    {
        $chapter->load([
            'currentVersion:id,chapter_id,version_number,content,source,is_current',
            'pendingVersion:id,chapter_id,version_number,content,source,change_summary,status',
            'scenes' => fn ($q) => $q->orderBy('sort_order'),
            'storyline:id,name,timeline_label',
            'povCharacter:id,name',
            'characters' => fn ($q) => $q->select('characters.id', 'characters.name'),
        ]);

        return response()->json([
            'chapter' => $chapter,
            'versionCount' => $chapter->versions()->count(),
            'prosePassRules' => Book::globalProsePassRules(),
            'proofreadingConfig' => Book::globalProofreadingConfig(),
            'customDictionary' => $book->custom_dictionary ?? [],
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
        $wordCount = WordCount::count($request->validated('content'));
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
            ->select('id', 'chapter_id', 'version_number', 'source', 'change_summary', 'is_current', 'status', 'created_at')
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
            $wordCount = WordCount::count($content);

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

            // Move specified scenes from the original chapter to the new one
            $sceneIds = $request->validated('scene_ids') ?? [];
            if (! empty($sceneIds)) {
                $scenesToMove = $chapter->scenes()
                    ->whereIn('id', $sceneIds)
                    ->orderBy('sort_order')
                    ->get();

                if ($scenesToMove->isNotEmpty()) {
                    // Bulk move scenes to new chapter with correct sort_order
                    $sortOrderCase = '';
                    $moveIds = [];
                    $sortOrder = 1; // First scene (from content) is 0
                    foreach ($scenesToMove as $scene) {
                        $id = (int) $scene->id;
                        $moveIds[] = $id;
                        $sortOrderCase .= "WHEN {$id} THEN {$sortOrder} ";
                        $sortOrder++;
                    }
                    $moveIdList = implode(',', $moveIds);
                    DB::statement(
                        "UPDATE scenes SET chapter_id = {$newChapter->id}, sort_order = CASE id {$sortOrderCase}END WHERE id IN ({$moveIdList})"
                    );

                    $wordCount += $scenesToMove->sum('word_count');
                    $newChapter->update(['word_count' => $wordCount]);

                    // Bulk recompact sort_order for remaining scenes in original chapter
                    $remaining = $chapter->scenes()->orderBy('sort_order')->pluck('id');
                    if ($remaining->isNotEmpty()) {
                        $reorderCase = '';
                        $reorderIds = [];
                        foreach ($remaining->values() as $index => $id) {
                            $id = (int) $id;
                            $reorderIds[] = $id;
                            $reorderCase .= "WHEN {$id} THEN {$index} ";
                        }
                        $reorderIdList = implode(',', $reorderIds);
                        DB::statement(
                            "UPDATE scenes SET sort_order = CASE id {$reorderCase}END WHERE id IN ({$reorderIdList})"
                        );
                    }

                    // Recalculate original chapter word count
                    $chapter->update([
                        'word_count' => $chapter->scenes()->sum('word_count'),
                    ]);
                }
            }

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
            $chapter->syncCurrentVersionContent();
            $chapter->versions()->update(['is_current' => false]);

            $latestVersionNumber = $chapter->versions()->max('version_number');

            $chapter->versions()->create([
                'version_number' => $latestVersionNumber + 1,
                'content' => $version->content,
                'source' => VersionSource::ManualEdit,
                'change_summary' => "Restored from version {$version->version_number}",
                'is_current' => true,
            ]);

            $chapter->replaceScenesWithContent($version->content);
        });

        return redirect()->back();
    }

    public function createSnapshot(CreateSnapshotRequest $request, Book $book, Chapter $chapter): JsonResponse
    {
        $version = DB::transaction(function () use ($request, $chapter) {
            $content = $chapter->syncCurrentVersionContent();

            $chapter->versions()->update(['is_current' => false]);
            $latestVersionNumber = $chapter->versions()->max('version_number');

            return $chapter->versions()->create([
                'version_number' => $latestVersionNumber + 1,
                'content' => $content,
                'source' => VersionSource::Snapshot,
                'change_summary' => $request->validated('change_summary'),
                'is_current' => true,
            ]);
        });

        return response()->json($version);
    }

    public function destroyVersion(Book $book, Chapter $chapter, ChapterVersion $version): JsonResponse
    {
        abort_if($version->is_current, 403, 'Cannot delete the current version.');
        abort_if($chapter->versions()->count() <= 1, 403, 'Cannot delete the last version.');

        $version->delete();

        return response()->json(['success' => true]);
    }

    public function acceptVersion(Book $book, Chapter $chapter, ChapterVersion $version): JsonResponse
    {
        abort_if($version->status !== VersionStatus::Pending, 403, 'Only pending versions can be accepted.');

        $this->applyVersion($chapter, $version, $version->content);

        return response()->json(['success' => true]);
    }

    public function acceptPartialVersion(Request $request, Book $book, Chapter $chapter, ChapterVersion $version): JsonResponse
    {
        abort_if($version->status !== VersionStatus::Pending, 403, 'Only pending versions can be accepted.');

        $request->validate([
            'content' => ['required', 'string'],
        ]);

        $this->applyVersion($chapter, $version, $request->input('content'));

        return response()->json(['success' => true]);
    }

    private function applyVersion(Chapter $chapter, ChapterVersion $version, string $content): void
    {
        DB::transaction(function () use ($chapter, $version, $content) {
            $chapter->syncCurrentVersionContent();
            $chapter->versions()->where('is_current', true)->update(['is_current' => false]);

            $version->update([
                'content' => $content,
                'is_current' => true,
                'status' => VersionStatus::Accepted,
            ]);

            $chapter->replaceSceneContents($content, $version->scene_map);
        });
    }

    public function rejectVersion(Book $book, Chapter $chapter, ChapterVersion $version): JsonResponse
    {
        abort_if($version->status !== VersionStatus::Pending, 403, 'Only pending versions can be rejected.');

        $version->delete();

        return response()->json(['success' => true]);
    }

    public function destroy(Book $book, Chapter $chapter): RedirectResponse
    {
        return DB::transaction(function () use ($book, $chapter) {
            $deletedOrder = $chapter->reader_order;
            $chapter->scenes()->delete();
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
        });
    }

    public function updateStatus(UpdateChapterStatusRequest $request, Book $book, Chapter $chapter): JsonResponse
    {
        $chapter->update(['status' => $request->validated('status')]);

        return response()->json([
            'status' => $chapter->status->value,
        ]);
    }

    public function assignAct(AssignChapterActRequest $request, Book $book, Chapter $chapter): JsonResponse
    {
        $chapter->update(['act_id' => $request->validated('act_id')]);

        return response()->json(['success' => true]);
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

    public function interleave(Book $book): JsonResponse
    {
        $chapters = $book->chapters()
            ->with(['act', 'storyline'])
            ->orderBy('reader_order')
            ->get();

        if ($chapters->isEmpty()) {
            return response()->json([]);
        }

        $storylineSortOrders = $book->storylines()
            ->pluck('sort_order', 'id');

        $actGroups = $chapters->groupBy(fn (Chapter $ch) => $ch->act_id ?? 'unassigned');

        $assignedActIds = $actGroups->keys()->filter(fn ($key) => $key !== 'unassigned');

        $sortedActIds = $assignedActIds->sort(function ($a, $b) use ($actGroups) {
            $actA = $actGroups[$a]->first()->act;
            $actB = $actGroups[$b]->first()->act;

            return $actA->sort_order <=> $actB->sort_order;
        });

        $ordered = collect();

        foreach ($sortedActIds as $actId) {
            $actChapters = $actGroups[$actId];
            $ordered = $ordered->merge($this->roundRobinByStoryline($actChapters, $storylineSortOrders));
        }

        if ($actGroups->has('unassigned')) {
            $unassigned = $actGroups['unassigned']->sortBy('reader_order')->values();
            $ordered = $ordered->merge($unassigned);
        }

        $result = [];
        $orderCase = '';
        $ids = [];

        foreach ($ordered->values() as $index => $chapter) {
            $id = (int) $chapter->id;
            $ids[] = $id;
            $orderCase .= "WHEN {$id} THEN {$index} ";
            $result[] = ['id' => $chapter->id, 'reader_order' => $index];
        }

        if (count($ids) > 0) {
            $idList = implode(',', $ids);
            DB::statement(
                "UPDATE chapters SET reader_order = CASE id {$orderCase}END WHERE id IN ({$idList})"
            );
        }

        return response()->json($result);
    }

    /**
     * @param  Collection<int, Chapter>  $chapters
     * @param  Collection<int, int>  $storylineSortOrders
     * @return Collection<int, Chapter>
     */
    private function roundRobinByStoryline($chapters, $storylineSortOrders): Collection
    {
        $byStoryline = $chapters->groupBy('storyline_id');

        $sortedStorylineIds = $byStoryline->keys()->sort(function ($a, $b) use ($storylineSortOrders) {
            return ($storylineSortOrders[$a] ?? PHP_INT_MAX) <=> ($storylineSortOrders[$b] ?? PHP_INT_MAX);
        });

        $queues = [];
        foreach ($sortedStorylineIds as $storylineId) {
            $queues[$storylineId] = $byStoryline[$storylineId]->sortBy('reader_order')->values()->all();
        }

        $result = collect();
        $hasMore = true;

        while ($hasMore) {
            $hasMore = false;
            foreach ($sortedStorylineIds as $storylineId) {
                if (! empty($queues[$storylineId])) {
                    $result->push(array_shift($queues[$storylineId]));
                    if (! empty($queues[$storylineId])) {
                        $hasMore = true;
                    }
                }
            }
        }

        return $result;
    }
}
