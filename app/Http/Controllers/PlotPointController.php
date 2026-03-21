<?php

namespace App\Http\Controllers;

use App\Enums\PlotPointStatus;
use App\Http\Requests\StorePlotPointRequest;
use App\Http\Requests\UpdatePlotPointRequest;
use App\Models\Book;
use App\Models\PlotPoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlotPointController extends Controller
{
    public function store(StorePlotPointRequest $request, Book $book): RedirectResponse
    {
        $nextOrder = ($book->plotPoints()->max('sort_order') ?? -1) + 1;

        $book->plotPoints()->create([
            ...$request->validated(),
            'sort_order' => $nextOrder,
        ]);

        return back();
    }

    public function update(UpdatePlotPointRequest $request, Book $book, PlotPoint $plotPoint): RedirectResponse
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

        return back();
    }

    public function destroy(Book $book, PlotPoint $plotPoint): RedirectResponse
    {
        $plotPoint->delete();

        return back();
    }

    public function reorder(Request $request, Book $book): RedirectResponse
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

        return back();
    }

    public function updateStatus(Request $request, Book $book, PlotPoint $plotPoint): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(PlotPointStatus::class)],
        ]);

        $plotPoint->update($validated);

        return back();
    }
}
