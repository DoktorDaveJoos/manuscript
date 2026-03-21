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
        $validated = $request->validated();
        $characters = null;

        if (array_key_exists('characters', $validated)) {
            $characters = collect($validated['characters'])->mapWithKeys(
                fn (array $item) => [$item['id'] => ['role' => $item['role']]]
            )->all();
            unset($validated['characters']);
        }

        $plotPoint->update($validated);

        if ($characters !== null) {
            $plotPoint->characters()->sync($characters);
        }

        $plotPoint->load(['act', 'characters']);

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
            'items.*.act_id' => ['sometimes', 'nullable', 'integer', Rule::exists('acts', 'id')->where('book_id', $book->id)],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['items'] as $item) {
                $data = ['sort_order' => $item['sort_order']];

                if (array_key_exists('act_id', $item)) {
                    $data['act_id'] = $item['act_id'];
                }

                PlotPoint::where('id', $item['id'])->update($data);
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
