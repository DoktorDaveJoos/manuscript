<?php

namespace App\Http\Controllers;

use App\Http\Requests\RestoreTrashRequest;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Scene;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TrashController extends Controller
{
    public function index(Book $book): JsonResponse
    {
        $storylines = $book->storylines()->onlyTrashed()
            ->select('id', 'name', 'color', 'deleted_at')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'type' => 'storyline',
                'name' => $s->name,
                'color' => $s->color,
                'deleted_at' => $s->deleted_at->toISOString(),
            ]);

        $chapters = Chapter::onlyTrashed()
            ->where('book_id', $book->id)
            ->whereHas('storyline')
            ->select('id', 'title', 'storyline_id', 'deleted_at')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'type' => 'chapter',
                'name' => $c->title,
                'deleted_at' => $c->deleted_at->toISOString(),
            ]);

        $scenes = Scene::onlyTrashed()
            ->whereIn('chapter_id', Chapter::where('book_id', $book->id)->select('id'))
            ->select('id', 'title', 'chapter_id', 'deleted_at')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'type' => 'scene',
                'name' => $s->title,
                'deleted_at' => $s->deleted_at->toISOString(),
            ]);

        $items = $storylines->concat($chapters)->concat($scenes)
            ->sortByDesc('deleted_at')
            ->values();

        return response()->json($items);
    }

    public function restore(RestoreTrashRequest $request, Book $book): JsonResponse
    {
        $type = $request->validated('type');
        $id = $request->validated('id');

        DB::transaction(function () use ($type, $id, $book) {
            match ($type) {
                'storyline' => $this->restoreStoryline($id, $book),
                'chapter' => $this->restoreChapter($id, $book),
                'scene' => $this->restoreScene($id, $book),
            };
        });

        return response()->json(['success' => true]);
    }

    public function empty(Book $book): JsonResponse
    {
        DB::transaction(function () use ($book) {
            Scene::onlyTrashed()
                ->whereIn('chapter_id', Chapter::withTrashed()->where('book_id', $book->id)->select('id'))
                ->forceDelete();

            Chapter::onlyTrashed()
                ->where('book_id', $book->id)
                ->forceDelete();

            $book->storylines()->onlyTrashed()->forceDelete();
        });

        return response()->json(['success' => true]);
    }

    private function restoreStoryline(int $id, Book $book): void
    {
        $storyline = $book->storylines()->onlyTrashed()->findOrFail($id);

        $nextSortOrder = ($book->storylines()->max('sort_order') ?? -1) + 1;
        $nextReaderOrder = ($book->chapters()->max('reader_order') ?? -1) + 1;

        $storyline->restore();

        $chapters = Chapter::onlyTrashed()
            ->where('storyline_id', $storyline->id)
            ->get();

        Chapter::onlyTrashed()
            ->where('storyline_id', $storyline->id)
            ->restore();

        Scene::onlyTrashed()
            ->whereIn('chapter_id', $chapters->pluck('id'))
            ->restore();

        $storyline->update(['sort_order' => $nextSortOrder]);

        foreach ($chapters as $chapter) {
            $chapter->update(['reader_order' => $nextReaderOrder++]);
        }
    }

    private function restoreChapter(int $id, Book $book): void
    {
        $chapter = Chapter::onlyTrashed()
            ->where('book_id', $book->id)
            ->findOrFail($id);

        // Auto-restore trashed storyline if needed
        $storyline = $chapter->storyline()->withTrashed()->first();
        if ($storyline && $storyline->trashed()) {
            $storyline->restore();
            $storyline->update([
                'sort_order' => ($book->storylines()->max('sort_order') ?? -1) + 1,
            ]);
        }

        $nextOrder = ($book->chapters()->max('reader_order') ?? -1) + 1;

        $chapter->restore();
        Scene::onlyTrashed()
            ->where('chapter_id', $chapter->id)
            ->restore();

        $chapter->update(['reader_order' => $nextOrder]);

        $chapter->recalculateWordCount();
    }

    private function restoreScene(int $id, Book $book): void
    {
        $scene = Scene::onlyTrashed()
            ->whereHas('chapter', fn ($q) => $q->where('book_id', $book->id))
            ->findOrFail($id);

        $scene->restore();

        $nextOrder = ($scene->chapter->scenes()->max('sort_order') ?? -1) + 1;
        $scene->update(['sort_order' => $nextOrder]);

        $scene->chapter->recalculateWordCount();
    }
}
