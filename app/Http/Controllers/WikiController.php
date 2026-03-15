<?php

namespace App\Http\Controllers;

use App\Enums\WikiEntryKind;
use App\Http\Requests\StoreCharacterRequest;
use App\Http\Requests\StoreWikiEntryRequest;
use App\Http\Requests\UpdateCharacterRequest;
use App\Http\Requests\UpdateWikiEntryRequest;
use App\Models\Book;
use App\Models\Character;
use App\Models\WikiEntry;
use Illuminate\Http\RedirectResponse;
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

    public function storeCharacter(StoreCharacterRequest $request, Book $book): RedirectResponse
    {
        $book->characters()->create([
            ...$request->safe()->except('role'),
            'is_ai_extracted' => false,
        ]);

        return redirect()->route('books.wiki', ['book' => $book, 'tab' => 'characters']);
    }

    public function updateCharacter(UpdateCharacterRequest $request, Book $book, Character $character): RedirectResponse
    {
        $character->update($request->safe()->except('role'));

        return redirect()->route('books.wiki', ['book' => $book, 'tab' => 'characters']);
    }

    public function destroyCharacter(Book $book, Character $character): RedirectResponse
    {
        $character->delete();

        return redirect()->route('books.wiki', ['book' => $book, 'tab' => 'characters']);
    }

    public function storeEntry(StoreWikiEntryRequest $request, Book $book): RedirectResponse
    {
        $data = $request->validated();
        $tab = $data['kind'];

        $book->wikiEntries()->create([
            ...$data,
            'is_ai_extracted' => false,
        ]);

        return redirect()->route('books.wiki', ['book' => $book, 'tab' => $tab]);
    }

    public function updateEntry(UpdateWikiEntryRequest $request, Book $book, WikiEntry $wikiEntry): RedirectResponse
    {
        $wikiEntry->update($request->validated());

        return redirect()->route('books.wiki', ['book' => $book, 'tab' => $wikiEntry->kind->value]);
    }

    public function destroyEntry(Book $book, WikiEntry $wikiEntry): RedirectResponse
    {
        $tab = $wikiEntry->kind->value;
        $wikiEntry->delete();

        return redirect()->route('books.wiki', ['book' => $book, 'tab' => $tab]);
    }
}
