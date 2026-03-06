<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Inertia\Inertia;
use Inertia\Response;

class CanvasController extends Controller
{
    public function index(Book $book): Response
    {
        $book->load([
            'storylines' => fn ($q) => $q->orderBy('sort_order'),
            'storylines.chapters' => fn ($q) => $q
                ->select('id', 'book_id', 'storyline_id', 'title', 'reader_order', 'status', 'word_count')
                ->orderBy('reader_order'),
        ]);

        return Inertia::render('canvas/index', [
            'book' => $book->only('id', 'title', 'storylines'),
        ]);
    }
}
