<?php

namespace App\Http\Controllers;

use App\Models\Beat;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class PlotPanelController extends Controller
{
    public function index(Request $request, Book $book): JsonResponse
    {
        $request->validate([
            'chapter_id' => ['required', 'integer', Rule::exists('chapters', 'id')->where('book_id', $book->id)],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $chapterId = (int) $request->input('chapter_id');
        $query = trim((string) $request->input('q', ''));

        $connectedBeats = Beat::query()
            ->whereHas('chapters', fn ($q) => $q->where('chapters.id', $chapterId))
            ->whereHas('plotPoint', fn ($q) => $q->where('book_id', $book->id))
            ->with('plotPoint')
            ->get();

        $sessionBeats = collect();
        if ($query !== '') {
            $sessionBeats = Beat::query()
                ->whereHas('plotPoint', fn ($q) => $q->where('book_id', $book->id))
                ->whereDoesntHave('chapters', fn ($q) => $q->where('chapters.id', $chapterId))
                ->where(fn ($qq) => $qq
                    ->where('title', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%"))
                ->with('plotPoint')
                ->limit(50)
                ->get();
        }

        return response()->json([
            'connected' => $this->groupByPlotPoint($connectedBeats),
            'session' => $this->groupByPlotPoint($sessionBeats),
        ]);
    }

    public function connect(Request $request, Book $book): JsonResponse
    {
        $data = $request->validate([
            'chapter_id' => ['required', 'integer', Rule::exists('chapters', 'id')->where('book_id', $book->id)],
            'beat_id' => ['required', 'integer'],
        ]);

        $beat = Beat::with('plotPoint')->findOrFail($data['beat_id']);
        abort_unless($beat->plotPoint->book_id === $book->id, 404);

        $beat->chapters()->syncWithoutDetaching([$data['chapter_id']]);

        return response()->json(['ok' => true]);
    }

    public function disconnect(Request $request, Book $book): JsonResponse
    {
        $data = $request->validate([
            'chapter_id' => ['required', 'integer', Rule::exists('chapters', 'id')->where('book_id', $book->id)],
            'beat_id' => ['required', 'integer'],
        ]);

        $beat = Beat::with('plotPoint')->findOrFail($data['beat_id']);
        abort_unless($beat->plotPoint->book_id === $book->id, 404);

        $beat->chapters()->detach($data['chapter_id']);

        return response()->json(['ok' => true]);
    }

    /**
     * @param  Collection<int, Beat>  $beats
     * @return array<int, array{plot_point: array{id:int,title:string,sort_order:int}, beats: array<int, array<string, mixed>>}>
     */
    private function groupByPlotPoint($beats): array
    {
        return $beats
            ->groupBy('plot_point_id')
            ->map(fn ($group) => [
                'plot_point' => [
                    'id' => $group->first()->plotPoint->id,
                    'title' => $group->first()->plotPoint->title,
                    'sort_order' => $group->first()->plotPoint->sort_order,
                ],
                'beats' => $group->map(fn (Beat $b) => $this->beatShape($b))->values()->all(),
            ])
            ->sortBy('plot_point.sort_order')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function beatShape(Beat $beat): array
    {
        return [
            'id' => $beat->id,
            'title' => $beat->title,
            'description' => $beat->description,
            'status' => $beat->status->value,
            'sort_order' => $beat->sort_order,
            'plot_point_id' => $beat->plot_point_id,
        ];
    }
}
