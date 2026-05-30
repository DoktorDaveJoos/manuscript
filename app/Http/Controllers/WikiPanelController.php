<?php

namespace App\Http\Controllers;

use App\Enums\CharacterRole;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\WikiEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WikiPanelController extends Controller
{
    public function index(Request $request, Book $book): JsonResponse
    {
        $request->validate([
            'chapter_id' => ['required', 'integer', Rule::exists('chapters', 'id')->where('book_id', $book->id)],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $chapterId = (int) $request->input('chapter_id');
        $query = $request->input('q');

        $response = [];

        if (! $query) {
            $characters = $book->characters()
                ->select(['id', 'book_id', 'name', 'description', 'ai_description', 'aliases'])
                ->whereHas('chapters', fn ($q) => $q->where('chapters.id', $chapterId))
                ->with(['chapters' => fn ($q) => $q->where('chapters.id', $chapterId)])
                ->get();

            $entries = $book->wikiEntries()
                ->select(['id', 'book_id', 'kind', 'name', 'type', 'description', 'ai_description'])
                ->whereHas('chapters', fn ($q) => $q->where('chapters.id', $chapterId))
                ->get();

            $response['connected'] = [
                'characters' => $characters,
                'entries' => $entries,
            ];
        } else {
            $connectedCharacterIds = $book->characters()
                ->whereHas('chapters', fn ($q) => $q->where('chapters.id', $chapterId))
                ->pluck('id');

            $connectedEntryIds = $book->wikiEntries()
                ->whereHas('chapters', fn ($q) => $q->where('chapters.id', $chapterId))
                ->pluck('id');

            $searchCharacters = $book->characters()
                ->select(['id', 'name', 'description', 'aliases'])
                ->where('name', 'like', "%{$query}%")
                ->whereNotIn('id', $connectedCharacterIds)
                ->limit(10)
                ->get()
                ->map(fn (Character $c) => [
                    'id' => $c->id,
                    'type' => 'character',
                    'name' => $c->name,
                    'kind' => 'character',
                    'entry_type' => null,
                    'description' => $c->description,
                    'aliases' => $c->aliases,
                ]);

            $searchEntries = $book->wikiEntries()
                ->select(['id', 'name', 'kind', 'type', 'description'])
                ->where('name', 'like', "%{$query}%")
                ->whereNotIn('id', $connectedEntryIds)
                ->limit(10)
                ->get()
                ->map(fn (WikiEntry $e) => [
                    'id' => $e->id,
                    'type' => 'wiki_entry',
                    'name' => $e->name,
                    'kind' => $e->kind->value,
                    'entry_type' => $e->type,
                    'description' => $e->description,
                    'aliases' => null,
                ]);

            $response['search_results'] = $searchCharacters
                ->concat($searchEntries)
                ->sortBy('name')
                ->values()
                ->take(10)
                ->all();
        }

        return response()->json($response);
    }

    public function connect(Request $request, Book $book): JsonResponse
    {
        $request->validate([
            'chapter_id' => ['required', 'integer', Rule::exists('chapters', 'id')->where('book_id', $book->id)],
            'type' => ['required', Rule::in(['character', 'wiki_entry'])],
            'id' => ['required', 'integer'],
            'role' => ['nullable', Rule::in(['protagonist', 'supporting', 'mentioned'])],
        ]);

        $chapterId = (int) $request->input('chapter_id');

        if ($request->input('type') === 'character') {
            $character = $book->characters()->findOrFail($request->input('id'));
            $role = $request->input('role', 'mentioned');
            $character->chapters()->syncWithoutDetaching([
                $chapterId => ['role' => $role],
            ]);
        } else {
            $entry = $book->wikiEntries()->findOrFail($request->input('id'));
            $entry->chapters()->syncWithoutDetaching([$chapterId]);
        }

        return response()->json(['success' => true]);
    }

    public function disconnect(Request $request, Book $book): JsonResponse
    {
        $request->validate([
            'chapter_id' => ['required', 'integer', Rule::exists('chapters', 'id')->where('book_id', $book->id)],
            'type' => ['required', Rule::in(['character', 'wiki_entry'])],
            'id' => ['required', 'integer'],
        ]);

        $chapterId = (int) $request->input('chapter_id');

        if ($request->input('type') === 'character') {
            $character = $book->characters()->findOrFail($request->input('id'));
            $character->chapters()->detach($chapterId);
        } else {
            $entry = $book->wikiEntries()->findOrFail($request->input('id'));
            $entry->chapters()->detach($chapterId);
        }

        return response()->json(['success' => true]);
    }

    public function updateCharacter(Request $request, Book $book, Character $character): JsonResponse
    {
        abort_unless($character->book_id === $book->id, 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'aliases' => ['nullable', 'array'],
            'aliases.*' => ['string', 'max:255'],
        ]);

        $character->update($data);

        return response()->json(['success' => true]);
    }

    public function updateWikiEntry(Request $request, Book $book, WikiEntry $wikiEntry): JsonResponse
    {
        abort_unless($wikiEntry->book_id === $book->id, 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $wikiEntry->update($data);

        return response()->json(['success' => true]);
    }

    public function updateRole(Request $request, Book $book, Character $character): JsonResponse
    {
        abort_unless($character->book_id === $book->id, 403);

        $request->validate([
            'chapter_id' => ['required', 'integer', Rule::exists('chapters', 'id')->where('book_id', $book->id)],
            'role' => ['required', Rule::in(['protagonist', 'supporting', 'mentioned'])],
        ]);

        $chapterId = (int) $request->input('chapter_id');
        $role = $request->input('role');

        DB::transaction(function () use ($character, $chapterId, $role): void {
            $chapter = Chapter::query()->findOrFail($chapterId);

            if ($role === CharacterRole::Protagonist->value) {
                $chapter->characters()
                    ->wherePivot('role', CharacterRole::Protagonist->value)
                    ->where('characters.id', '!=', $character->id)
                    ->get()
                    ->each(function (Character $existing) use ($chapter): void {
                        $chapter->characters()->updateExistingPivot($existing->id, [
                            'role' => CharacterRole::Supporting->value,
                        ]);
                    });

                $character->chapters()->updateExistingPivot($chapterId, ['role' => $role]);

                $chapter->update(['pov_character_id' => $character->id]);

                return;
            }

            $character->chapters()->updateExistingPivot($chapterId, ['role' => $role]);

            if ($chapter->pov_character_id === $character->id) {
                $chapter->update(['pov_character_id' => null]);
            }
        });

        return response()->json(['success' => true]);
    }
}
