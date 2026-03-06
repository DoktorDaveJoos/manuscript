<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderScenesRequest;
use App\Http\Requests\StoreSceneRequest;
use App\Http\Requests\UpdateSceneContentRequest;
use App\Http\Requests\UpdateSceneTitleRequest;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Scene;
use Illuminate\Http\JsonResponse;

class SceneController extends Controller
{
    public function store(StoreSceneRequest $request, Book $book, Chapter $chapter): JsonResponse
    {
        $position = $request->validated('position') ?? $chapter->scenes()->count();

        // Shift existing scenes at or after position
        $chapter->scenes()
            ->where('sort_order', '>=', $position)
            ->increment('sort_order');

        $scene = $chapter->scenes()->create([
            'title' => $request->validated('title'),
            'content' => '',
            'sort_order' => $position,
            'word_count' => 0,
        ]);

        return response()->json($scene, 201);
    }

    public function updateContent(UpdateSceneContentRequest $request, Book $book, Chapter $chapter, Scene $scene): JsonResponse
    {
        $wordCount = str_word_count(strip_tags($request->validated('content')));

        $scene->update([
            'content' => $request->validated('content'),
            'word_count' => $wordCount,
        ]);

        $chapter->recalculateWordCount();

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
        $order = $request->validated('order');

        foreach ($order as $index => $sceneId) {
            $chapter->scenes()->where('id', $sceneId)->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }

    public function destroy(Book $book, Chapter $chapter, Scene $scene): JsonResponse
    {
        if ($chapter->scenes()->count() <= 1) {
            return response()->json(['error' => 'Cannot delete the last scene'], 422);
        }

        $scene->delete();
        $chapter->recalculateWordCount();

        // Recompact sort_order
        $chapter->scenes()->orderBy('sort_order')->get()->each(function (Scene $s, int $index) {
            $s->update(['sort_order' => $index]);
        });

        return response()->json(['success' => true]);
    }
}
