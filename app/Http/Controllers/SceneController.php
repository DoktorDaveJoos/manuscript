<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderScenesRequest;
use App\Http\Requests\StoreSceneRequest;
use App\Http\Requests\UpdateSceneContentRequest;
use App\Http\Requests\UpdateSceneTitleRequest;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Scene;
use App\Models\WritingSession;
use App\Support\WordCount;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SceneController extends Controller
{
    public function store(StoreSceneRequest $request, Book $book, Chapter $chapter): JsonResponse
    {
        $position = $request->validated('position') ?? $chapter->scenes()->count();

        // Shift existing scenes at or after position
        $chapter->scenes()
            ->where('sort_order', '>=', $position)
            ->increment('sort_order');

        $content = $request->validated('content') ?? '';
        $wordCount = $content !== '' ? WordCount::count($content) : 0;

        $scene = $chapter->scenes()->create([
            'title' => $request->validated('title'),
            'content' => $content,
            'sort_order' => $position,
            'word_count' => $wordCount,
        ]);

        if ($wordCount > 0) {
            $chapter->recalculateWordCount();
        }

        return response()->json($scene, 201);
    }

    public function updateContent(UpdateSceneContentRequest $request, Book $book, Chapter $chapter, Scene $scene): JsonResponse
    {
        $previousSceneWordCount = $scene->word_count;
        $wordCount = WordCount::count($request->validated('content'));

        $scene->update([
            'content' => $request->validated('content'),
            'word_count' => $wordCount,
        ]);

        $chapter->recalculateWordCount();

        $delta = $wordCount - $previousSceneWordCount;
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
            'chapter_word_count' => $chapter->fresh()->word_count,
            'saved_at' => now()->toISOString(),
        ]);
    }

    public function updateTitle(UpdateSceneTitleRequest $request, Book $book, Chapter $chapter, Scene $scene): JsonResponse
    {
        $scene->update(['title' => $request->validated('title')]);

        return response()->json([
            'title' => $scene->title,
            'saved_at' => now()->toISOString(),
        ]);
    }

    public function reorder(ReorderScenesRequest $request, Book $book, Chapter $chapter): JsonResponse
    {
        DB::transaction(function () use ($request, $chapter) {
            $order = $request->validated('order');

            if (count($order) > 0) {
                $cases = '';
                $ids = [];
                foreach ($order as $index => $sceneId) {
                    $id = (int) $sceneId;
                    $ids[] = $id;
                    $cases .= "WHEN {$id} THEN {$index} ";
                }
                $idList = implode(',', $ids);
                DB::statement("UPDATE scenes SET sort_order = CASE id {$cases}END WHERE id IN ({$idList})");
            }

            $chapter->refreshContentHash();
        });

        return response()->json(['success' => true]);
    }

    public function destroy(Book $book, Chapter $chapter, Scene $scene): JsonResponse
    {
        if ($chapter->scenes()->count() <= 1) {
            return response()->json(['error' => __('Cannot delete the last scene')], 422);
        }

        DB::transaction(function () use ($chapter, $scene) {
            $scene->delete();
            $chapter->recalculateWordCount();

            // Recompact sort_order in a single query
            $remaining = $chapter->scenes()->orderBy('sort_order')->pluck('id');
            if ($remaining->isNotEmpty()) {
                $cases = '';
                $ids = [];
                foreach ($remaining as $index => $id) {
                    $ids[] = $id;
                    $cases .= "WHEN {$id} THEN {$index} ";
                }
                $idList = implode(',', $ids);
                DB::statement("UPDATE scenes SET sort_order = CASE id {$cases}END WHERE id IN ({$idList})");
            }
        });

        return response()->json(['success' => true]);
    }
}
