<?php

namespace App\Http\Controllers;

use App\Enums\PlotPointStatus;
use App\Http\Requests\StorePlotPointRequest;
use App\Http\Requests\UpdatePlotPointRequest;
use App\Models\Book;
use App\Models\PlotPoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlotPointController extends Controller
{
    public function store(StorePlotPointRequest $request, Book $book): JsonResponse
    {
        $nextOrder = ($book->plotPoints()->max('sort_order') ?? -1) + 1;

        $plotPoint = $book->plotPoints()->create([
            ...$request->validated(),
            'sort_order' => $nextOrder,
        ]);

        $plotPoint->load(['act']);

        return response()->json($plotPoint, 201);
    }

    public function update(UpdatePlotPointRequest $request, Book $book, PlotPoint $plotPoint): JsonResponse
    {
        $plotPoint->update($request->validated());

        $plotPoint->load(['act']);

        return response()->json($plotPoint);
    }

    public function destroy(Book $book, PlotPoint $plotPoint): JsonResponse
    {
        $plotPoint->delete();

        return response()->json(null, 204);
    }

    public function reorder(Request $request, Book $book): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', Rule::exists('plot_points', 'id')->where('book_id', $book->id)],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['items'] as $item) {
                PlotPoint::where('id', $item['id'])->update([
                    'sort_order' => $item['sort_order'],
                ]);
            }
        });

        return response()->json(['success' => true]);
    }

    public function updateStatus(Request $request, Book $book, PlotPoint $plotPoint): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(PlotPointStatus::class)],
        ]);

        $plotPoint->update($validated);

        return response()->json($plotPoint);
    }
}
