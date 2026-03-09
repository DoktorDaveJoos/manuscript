<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlotPointConnectionRequest;
use App\Models\Book;
use App\Models\PlotPointConnection;
use Illuminate\Http\JsonResponse;

class PlotPointConnectionController extends Controller
{
    public function store(StorePlotPointConnectionRequest $request, Book $book): JsonResponse
    {
        $connection = $book->plotPointConnections()->create($request->validated());

        $connection->load(['source', 'target']);

        return response()->json($connection, 201);
    }

    public function destroy(Book $book, PlotPointConnection $plotPointConnection): JsonResponse
    {
        $plotPointConnection->delete();

        return response()->json(null, 204);
    }
}
