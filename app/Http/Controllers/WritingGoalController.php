<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateWritingGoalRequest;
use App\Models\Book;
use Illuminate\Http\JsonResponse;

class WritingGoalController extends Controller
{
    public function update(UpdateWritingGoalRequest $request, Book $book): JsonResponse
    {
        $data = ['daily_word_count_goal' => $request->validated('daily_word_count_goal')];

        if ($request->has('target_word_count')) {
            $data['target_word_count'] = $request->validated('target_word_count');
        }

        $book->update($data);

        return response()->json([
            'daily_word_count_goal' => $book->daily_word_count_goal,
            'target_word_count' => $book->target_word_count,
        ]);
    }
}
