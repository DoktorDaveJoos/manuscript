<?php

namespace App\Http\Controllers;

use App\Enums\ChapterStatus;
use App\Enums\StorylineType;
use App\Enums\VersionSource;
use App\Http\Requests\ReorderStorylinesRequest;
use App\Http\Requests\StoreStorylineRequest;
use App\Http\Requests\UpdateStorylineRequest;
use App\Models\Book;
use App\Models\Scene;
use App\Models\Storyline;
use App\Services\FreeTierLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class StorylineController extends Controller
{
    public function store(StoreStorylineRequest $request, Book $book): RedirectResponse
    {
        if (! FreeTierLimits::canCreateStoryline($book)) {
            return redirect()->route('books.editor', $book)
                ->with('error', __('Upgrade to Manuscript Pro to add more storylines.'));
        }

        $chapter = DB::transaction(function () use ($request, $book) {
            $nextOrder = ($book->storylines()->max('sort_order') ?? -1) + 1;

            $storyline = $book->storylines()->create([
                'name' => $request->validated('name'),
                'type' => StorylineType::Parallel,
                'sort_order' => $nextOrder,
            ]);

            $nextChapterOrder = ($book->chapters()->max('reader_order') ?? -1) + 1;

            $chapter = $book->chapters()->create([
                'storyline_id' => $storyline->id,
                'title' => __('Chapter 1'),
                'reader_order' => $nextChapterOrder,
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
                'title' => __('Scene 1'),
                'content' => '',
                'sort_order' => 0,
                'word_count' => 0,
            ]);

            return $chapter;
        });

        return redirect()->route('chapters.show', [$book, $chapter]);
    }

    public function update(UpdateStorylineRequest $request, Book $book, Storyline $storyline): JsonResponse
    {
        $storyline->update($request->validated());

        return response()->json([
            'name' => $storyline->name,
            'color' => $storyline->color,
        ]);
    }

    public function destroy(Book $book, Storyline $storyline): RedirectResponse
    {
        if ($book->storylines()->count() <= 1) {
            abort(422, __('Cannot delete the last storyline.'));
        }

        DB::transaction(function () use ($storyline) {
            Scene::whereIn('chapter_id', $storyline->chapters()->select('id'))->delete();
            $storyline->chapters()->delete();
            $storyline->delete();
        });

        $firstChapter = $book->chapters()
            ->orderBy('reader_order')
            ->first();

        if ($firstChapter) {
            return redirect()->route('chapters.show', [$book, $firstChapter]);
        }

        return redirect()->route('books.editor', $book);
    }

    public function reorder(ReorderStorylinesRequest $request, Book $book): JsonResponse
    {
        $order = $request->validated('order');

        if (count($order) > 0) {
            $case = '';
            $ids = [];

            foreach ($order as $index => $storylineId) {
                $id = (int) $storylineId;
                $ids[] = $id;
                $case .= "WHEN {$id} THEN {$index} ";
            }

            $idList = implode(',', $ids);

            DB::statement("UPDATE storylines SET sort_order = CASE id {$case}END WHERE id IN ({$idList})");
        }

        return response()->json(['success' => true]);
    }
}
