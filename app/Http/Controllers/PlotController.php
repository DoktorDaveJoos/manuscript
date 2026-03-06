<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Inertia\Inertia;
use Inertia\Response;

class PlotController extends Controller
{
    public function index(Book $book): Response
    {
        $book->load([
            'storylines' => fn ($q) => $q->orderBy('sort_order'),
            'storylines.chapters' => fn ($q) => $q
                ->select('id', 'book_id', 'storyline_id', 'title', 'reader_order', 'status', 'word_count')
                ->orderBy('reader_order'),
            'acts' => fn ($q) => $q->orderBy('sort_order'),
            'plotPoints',
        ]);

        return Inertia::render('plot/index', [
            'book' => $book->only('id', 'title', 'storylines'),
            'acts' => $book->acts,
            'plotPoints' => $book->plotPoints,
        ]);
    }
}
