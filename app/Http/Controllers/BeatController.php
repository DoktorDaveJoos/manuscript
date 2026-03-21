<?php

namespace App\Http\Controllers;

use App\Enums\BeatStatus;
use App\Http\Requests\StoreBeatRequest;
use App\Http\Requests\UpdateBeatRequest;
use App\Models\Beat;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\PlotPoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BeatController extends Controller
{
    public function store(StoreBeatRequest $request, Book $book, PlotPoint $plotPoint): JsonResponse
    {
        $nextOrder = ($plotPoint->beats()->max('sort_order') ?? -1) + 1;

        $beat = $plotPoint->beats()->create([
            ...$request->validated(),
            'sort_order' => $nextOrder,
        ]);

        return response()->json($beat->refresh(), 201);
    }

    public function update(UpdateBeatRequest $request, Book $book, Beat $beat): JsonResponse
    {
        abort_unless($beat->plotPoint->book_id === $book->id, 404);
        $beat->update($request->validated());

        return response()->json($beat);
    }

    public function destroy(Book $book, Beat $beat): JsonResponse
    {
        abort_unless($beat->plotPoint->book_id === $book->id, 404);
        $beat->delete();

        return response()->json(null, 204);
    }

    public function reorder(Request $request, Book $book, PlotPoint $plotPoint): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', Rule::exists('beats', 'id')->where('plot_point_id', $plotPoint->id)],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['items'] as $item) {
                Beat::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
            }
        });

        return response()->json(['success' => true]);
    }

    public function move(Request $request, Book $book, Beat $beat): JsonResponse
    {
        abort_unless($beat->plotPoint->book_id === $book->id, 404);

        $validated = $request->validate([
            'plot_point_id' => ['required', 'integer', 'exists:plot_points,id'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $targetPlotPoint = PlotPoint::findOrFail($validated['plot_point_id']);
        abort_unless($targetPlotPoint->book_id === $book->id, 403);

        $beat->update([
            'plot_point_id' => $validated['plot_point_id'],
            'sort_order' => $validated['sort_order'],
        ]);

        return response()->json($beat);
    }

    public function updateStatus(Request $request, Book $book, Beat $beat): JsonResponse
    {
        abort_unless($beat->plotPoint->book_id === $book->id, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(BeatStatus::class)],
        ]);

        $beat->update($validated);

        return response()->json($beat);
    }

    public function linkChapter(Request $request, Book $book, Beat $beat): JsonResponse
    {
        abort_unless($beat->plotPoint->book_id === $book->id, 404);

        $validated = $request->validate([
            'chapter_id' => ['required', Rule::exists('chapters', 'id')->where('book_id', $book->id)],
        ]);

        $beat->chapters()->syncWithoutDetaching([$validated['chapter_id']]);

        return response()->json(['success' => true]);
    }

    public function unlinkChapter(Book $book, Beat $beat, Chapter $chapter): JsonResponse
    {
        abort_unless($beat->plotPoint->book_id === $book->id, 404);
        $beat->chapters()->detach($chapter->id);

        return response()->json(null, 204);
    }
}
