<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateWritingGoalRequest;
use App\Models\Book;
use Illuminate\Http\JsonResponse;

class WritingGoalController extends Controller
{
    public function update(UpdateWritingGoalRequest $request, Book $book): JsonResponse
    {
        $book->update([
            'daily_word_count_goal' => $request->validated('daily_word_count_goal'),
        ]);

        return response()->json([
            'daily_word_count_goal' => $book->daily_word_count_goal,
        ]);
    }
}
