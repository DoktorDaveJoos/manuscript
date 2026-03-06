<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderStorylinesRequest;
use App\Http\Requests\UpdateStorylineRequest;
use App\Models\Book;
use App\Models\Storyline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class StorylineController extends Controller
{
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
            abort(422, 'Cannot delete the last storyline.');
        }

        $storyline->chapters()->delete();
        $storyline->delete();

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
