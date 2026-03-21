<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreActRequest;
use App\Http\Requests\UpdateActRequest;
use App\Models\Act;
use App\Models\Book;
use Illuminate\Http\RedirectResponse;

class ActController extends Controller
{
    public function store(StoreActRequest $request, Book $book): RedirectResponse
    {
        $nextOrder = ($book->acts()->max('sort_order') ?? -1) + 1;

        $book->acts()->create([
            ...$request->validated(),
            'sort_order' => $nextOrder,
        ]);

        return back();
    }

    public function update(UpdateActRequest $request, Book $book, Act $act): RedirectResponse
    {
        abort_unless($act->book_id === $book->id, 404);
        $act->update($request->validated());

        return back();
    }

    public function destroy(Book $book, Act $act): RedirectResponse
    {
        abort_unless($act->book_id === $book->id, 404);
        $act->delete();

        return back();
    }
}
