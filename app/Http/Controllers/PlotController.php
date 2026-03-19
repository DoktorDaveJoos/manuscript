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
            'acts' => fn ($q) => $q->orderBy('sort_order'),
            'acts.chapters' => fn ($q) => $q->orderBy('reader_order')
                ->select('id', 'book_id', 'act_id', 'storyline_id', 'title', 'reader_order', 'status', 'word_count', 'tension_score'),
            'plotPoints' => fn ($q) => $q->orderBy('sort_order'),
            'plotPoints.characters',
            'plotPointConnections.source',
            'plotPointConnections.target',
        ]);

        $chapters = $book->chapters()
            ->orderBy('reader_order')
            ->select('id', 'book_id', 'act_id', 'storyline_id', 'title', 'reader_order', 'status', 'word_count')
            ->get();

        $characters = $book->characters()->orderBy('name')->get();

        return Inertia::render('plot/index', [
            'book' => $book,
            'storylines' => $book->storylines,
            'acts' => $book->acts,
            'plotPoints' => $book->plotPoints,
            'connections' => $book->plotPointConnections,
            'chapters' => $chapters,
            'characters' => $characters,
        ]);
    }
}
