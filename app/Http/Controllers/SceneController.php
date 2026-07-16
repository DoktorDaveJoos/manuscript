<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresChapterVersion;
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
    use EnsuresChapterVersion;

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
        // Autosave retries can be composed against a chapter the server has
        // since replaced (AI revision applied, version restored). When the
        // editor declares which version it was writing against, refuse stale
        // writes instead of resurrecting pre-revision content. Absent field
        // means a legacy caller — the write goes through unguarded.
        $expectedVersionId = $request->validated('expected_current_version_id');
        if ($expectedVersionId !== null) {
            $chapter->loadMissing('currentVersion');
            $this->ensureCurrentVersion($chapter, (int) $expectedVersionId);
        }

        $content = $request->validated('content');
        $wordCount = WordCount::count($content);
        $expectedContentVersion = (int) $request->validated('expected_content_version');
        $newContentVersion = $expectedContentVersion + 1;

        $result = DB::transaction(function () use (
            $book,
            $chapter,
            $scene,
            $content,
            $wordCount,
            $expectedContentVersion,
            $newContentVersion,
        ): ?array {
            $currentScene = Scene::query()
                ->whereKey($scene->getKey())
                ->firstOrFail(['id', 'word_count', 'content_version']);

            if ($currentScene->content_version !== $expectedContentVersion) {
                return null;
            }

            $updated = Scene::query()
                ->whereKey($scene->getKey())
                ->where('content_version', $expectedContentVersion)
                ->update([
                    'content' => $content,
                    'word_count' => $wordCount,
                    'content_version' => $newContentVersion,
                ]);

            if ($updated !== 1) {
                return null;
            }

            $chapter->recalculateWordCount();

            $delta = $wordCount - $currentScene->word_count;
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
            } elseif ($delta < 0) {
                $session = $book->writingSessions()
                    ->whereDate('date', now()->toDateString())
                    ->first();

                if ($session) {
                    $session->update([
                        'words_written' => max(0, $session->words_written + $delta),
                    ]);
                }
            }

            return [
                'chapter_word_count' => $chapter->fresh()->word_count,
            ];
        });

        if ($result === null) {
            return response()->json([
                'message' => __('This scene has changed since you started — retry with the latest content version.'),
                'conflict' => 'content_version',
                'content_version' => (int) Scene::query()
                    ->whereKey($scene->getKey())
                    ->value('content_version'),
            ], 409);
        }

        return response()->json([
            'word_count' => $wordCount,
            'chapter_word_count' => $result['chapter_word_count'],
            'content_version' => $newContentVersion,
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
        $deleted = DB::transaction(function () use ($chapter, $scene): bool {
            $sceneIds = $chapter->scenes()
                ->lockForUpdate()
                ->pluck('id');

            if ($sceneIds->count() <= 1) {
                return false;
            }

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

            return true;
        });

        if (! $deleted) {
            return response()->json(['error' => __('Cannot delete the last scene')], 422);
        }

        return response()->json(['success' => true]);
    }
}
