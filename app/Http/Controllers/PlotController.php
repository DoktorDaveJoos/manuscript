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
            'plotPoints' => fn ($q) => $q->orderBy('sort_order'),
            'plotPoints.beats' => fn ($q) => $q->orderBy('sort_order'),
            'plotPoints.beats.chapters:id,title,reader_order',
            'plotPoints.characters',
        ]);

        $characters = $book->characters()->orderBy('name')->get();

        return Inertia::render('plot/index', [
            'book' => $book,
            'storylines' => $book->storylines,
            'acts' => $book->acts,
            'plotPoints' => $book->plotPoints,
            'characters' => $characters,
        ]);
    }
}
