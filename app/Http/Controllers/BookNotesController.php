<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBookNotesRequest;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class BookNotesController extends Controller
{
    public function index(Book $book): Response
    {
        return Inertia::render('books/notes', [
            'book' => $book->only('id', 'title', 'notes', 'notes_version'),
        ]);
    }

    public function update(UpdateBookNotesRequest $request, Book $book): JsonResponse
    {
        $expectedVersion = (int) $request->validated('expected_version');
        $newVersion = $expectedVersion + 1;

        $updated = Book::query()
            ->whereKey($book->getKey())
            ->where('notes_version', $expectedVersion)
            ->update([
                'notes' => $request->validated('notes'),
                'notes_version' => $newVersion,
            ]);

        if ($updated !== 1) {
            return response()->json([
                'message' => __('These notes changed since you started editing.'),
                'conflict' => 'notes_version',
                'notes_version' => (int) Book::query()
                    ->whereKey($book->getKey())
                    ->value('notes_version'),
            ], 409);
        }

        return response()->json([
            'notes_version' => $newVersion,
            'saved_at' => now()->toISOString(),
        ]);
    }
}
