<?php

namespace App\Http\Controllers;

use App\Enums\BeatStatus;
use App\Http\Requests\StoreBeatRequest;
use App\Http\Requests\UpdateBeatRequest;
use App\Models\Beat;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\PlotPoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BeatController extends Controller
{
    public function store(StoreBeatRequest $request, Book $book, PlotPoint $plotPoint): RedirectResponse
    {
        $nextOrder = ($plotPoint->beats()->max('sort_order') ?? -1) + 1;

        $plotPoint->beats()->create([
            ...$request->validated(),
            'sort_order' => $nextOrder,
        ]);

        return back();
    }

    public function update(UpdateBeatRequest $request, Book $book, Beat $beat): RedirectResponse
    {
        abort_unless($beat->plotPoint->book_id === $book->id, 404);
        $beat->update($request->validated());

        return back();
    }

    public function destroy(Book $book, Beat $beat): RedirectResponse
    {
        abort_unless($beat->plotPoint->book_id === $book->id, 404);
        $beat->delete();

        return back();
    }

    public function reorder(Request $request, Book $book, PlotPoint $plotPoint): RedirectResponse
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

        return back();
    }

    public function move(Request $request, Book $book, Beat $beat): RedirectResponse
    {
        abort_unless($beat->plotPoint?->book_id === $book->id, 404);

        $validated = $request->validate([
            'plot_point_id' => ['required', 'integer', 'exists:plot_points,id'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $targetPlotPoint = PlotPoint::findOrFail($validated['plot_point_id']);
        abort_unless($targetPlotPoint->book_id === $book->id, 403);

        DB::transaction(function () use ($beat, $targetPlotPoint, $validated) {
            $sourcePlotPointId = $beat->plot_point_id;
            $targetPlotPointId = $targetPlotPoint->id;

            $targetSiblings = Beat::query()
                ->where('plot_point_id', $targetPlotPointId)
                ->where('id', '!=', $beat->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->values();

            $insertAt = min((int) $validated['sort_order'], $targetSiblings->count());
            $targetSiblings->splice($insertAt, 0, [$beat]);

            foreach ($targetSiblings as $index => $item) {
                $updates = ['sort_order' => $index];

                if ($item->id === $beat->id && $sourcePlotPointId !== $targetPlotPointId) {
                    $updates['plot_point_id'] = $targetPlotPointId;
                }

                $item->update($updates);
            }

            if ($sourcePlotPointId !== $targetPlotPointId) {
                $sourceSiblings = Beat::query()
                    ->where('plot_point_id', $sourcePlotPointId)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->values();

                foreach ($sourceSiblings as $index => $item) {
                    if ($item->sort_order !== $index) {
                        $item->update(['sort_order' => $index]);
                    }
                }
            }
        });

        return back();
    }

    public function updateStatus(Request $request, Book $book, Beat $beat): RedirectResponse
    {
        abort_unless($beat->plotPoint->book_id === $book->id, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(BeatStatus::class)],
        ]);

        $beat->update($validated);

        return back();
    }

    public function linkChapter(Request $request, Book $book, Beat $beat): RedirectResponse
    {
        abort_unless($beat->plotPoint->book_id === $book->id, 404);

        $validated = $request->validate([
            'chapter_id' => ['required', Rule::exists('chapters', 'id')->where('book_id', $book->id)],
        ]);

        $beat->chapters()->syncWithoutDetaching([$validated['chapter_id']]);

        return back();
    }

    public function unlinkChapter(Book $book, Beat $beat, Chapter $chapter): RedirectResponse
    {
        abort_unless($beat->plotPoint->book_id === $book->id, 404);
        $beat->chapters()->detach($chapter->id);

        return back();
    }
}
