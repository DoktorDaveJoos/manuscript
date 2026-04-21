<?php

namespace App\Http\Controllers;

use App\Enums\PlotCoachSessionStatus;
use App\Models\Book;
use App\Models\PlotCoachSession;
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
            'plotPoints.beats.chapters:id,title,storyline_id,reader_order',
            'plotPoints.characters',
        ]);

        $characters = $book->characters()->orderBy('name')->get();

        $chapters = $book->chapters()
            ->select('id', 'title', 'storyline_id', 'reader_order')
            ->with('storyline:id,name')
            ->orderBy('reader_order')
            ->get();

        $activeCoachSession = PlotCoachSession::query()
            ->where('book_id', $book->id)
            ->where('status', PlotCoachSessionStatus::Active)
            ->exists();

        return Inertia::render('plot/index', [
            'book' => $book,
            'storylines' => $book->storylines,
            'acts' => $book->acts,
            'plotPoints' => $book->plotPoints,
            'characters' => $characters,
            'chapters' => $chapters,
            'active_coach_session' => $activeCoachSession,
        ]);
    }
}
