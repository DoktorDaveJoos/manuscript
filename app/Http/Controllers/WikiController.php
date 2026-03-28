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
use App\Services\FreeTierLimits;
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
        if (! FreeTierLimits::canCreateWikiEntry($book)) {
            return redirect()->route('books.wiki', ['book' => $book, 'tab' => 'characters'])
                ->with('error', __('Upgrade to Manuscript Pro for unlimited Story Bible entries.'));
        }

        $character = $book->characters()->create([
            ...$request->safe()->except(['role', 'chapter_ids']),
            'is_ai_extracted' => false,
        ]);

        $this->syncCharacterChapters($character, $request);

        return redirect()->route('books.wiki', ['book' => $book, 'tab' => 'characters']);
    }

    public function updateCharacter(UpdateCharacterRequest $request, Book $book, Character $character): RedirectResponse
    {
        $character->update($request->safe()->except(['role', 'chapter_ids']));

        $this->syncCharacterChapters($character, $request);

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

        if (! FreeTierLimits::canCreateWikiEntry($book)) {
            return redirect()->route('books.wiki', ['book' => $book, 'tab' => $tab])
                ->with('error', __('Upgrade to Manuscript Pro for unlimited Story Bible entries.'));
        }

        $chapterIds = $data['chapter_ids'] ?? [];
        unset($data['chapter_ids']);

        $entry = $book->wikiEntries()->create([
            ...$data,
            'is_ai_extracted' => false,
        ]);

        if ($request->has('chapter_ids')) {
            $entry->chapters()->sync($chapterIds);
        }

        return redirect()->route('books.wiki', ['book' => $book, 'tab' => $tab]);
    }

    public function updateEntry(UpdateWikiEntryRequest $request, Book $book, WikiEntry $wikiEntry): RedirectResponse
    {
        $data = $request->validated();
        $chapterIds = $data['chapter_ids'] ?? [];
        unset($data['chapter_ids']);

        $wikiEntry->update($data);

        if ($request->has('chapter_ids')) {
            $wikiEntry->chapters()->sync($chapterIds);
        }

        return redirect()->route('books.wiki', ['book' => $book, 'tab' => $wikiEntry->kind->value]);
    }

    public function destroyEntry(Book $book, WikiEntry $wikiEntry): RedirectResponse
    {
        $tab = $wikiEntry->kind->value;
        $wikiEntry->delete();

        return redirect()->route('books.wiki', ['book' => $book, 'tab' => $tab]);
    }

    private function syncCharacterChapters(Character $character, Request $request): void
    {
        if ($request->has('chapter_ids')) {
            $role = $request->input('role', 'supporting');
            $pivotData = collect($request->input('chapter_ids', []))
                ->mapWithKeys(fn ($id) => [$id => ['role' => $role]])
                ->all();
            $character->chapters()->sync($pivotData);
        }
    }
}
