<?php

namespace App\Http\Controllers;

use App\Enums\WikiEntryKind;
use App\Models\Book;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WikiController extends Controller
{
    public function index(Book $book, Request $request): Response
    {
        $validTabs = ['characters', 'location', 'organization', 'item', 'lore'];
        $tab = in_array($request->query('tab'), $validTabs, true)
            ? $request->query('tab')
            : 'characters';

        $book->load([
            'storylines' => fn ($q) => $q->orderBy('sort_order'),
            'storylines.chapters' => fn ($q) => $q
                ->select('id', 'book_id', 'storyline_id', 'title', 'reader_order', 'status', 'word_count')
                ->orderBy('reader_order'),
        ]);

        $characters = $book->characters()
            ->with(['chapters', 'firstAppearanceChapter'])
            ->withCount('chapters as appearance_count')
            ->withCount(['chapters as protagonist_count' => fn ($q) => $q->where('character_chapter.role', 'protagonist')])
            ->orderByDesc('protagonist_count')
            ->orderByDesc('appearance_count')
            ->orderBy('name')
            ->get();

        $wikiEntries = $book->wikiEntries()
            ->with(['chapters', 'firstAppearanceChapter'])
            ->withCount('chapters as appearance_count')
            ->orderByDesc('appearance_count')
            ->orderBy('name')
            ->get();

        return Inertia::render('wiki/index', [
            'book' => $book->only('id', 'title', 'storylines'),
            'characters' => $characters,
            'locations' => $wikiEntries->where('kind', WikiEntryKind::Location)->values(),
            'organizations' => $wikiEntries->where('kind', WikiEntryKind::Organization)->values(),
            'items' => $wikiEntries->where('kind', WikiEntryKind::Item)->values(),
            'lore' => $wikiEntries->where('kind', WikiEntryKind::Lore)->values(),
            'tab' => $tab,
        ]);
    }
}
