<?php

namespace App\Http\Controllers;

use App\Enums\StorylineType;
use App\Enums\VersionSource;
use App\Http\Requests\ConfirmImportRequest;
use App\Http\Requests\ParseImportRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Chunk;
use App\Services\Normalization\NormalizationService;
use App\Services\Parsers\DocumentParserFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BookController extends Controller
{
    public function index(): Response
    {
        $books = Book::query()
            ->withCount([
                'chapters',
                'chapters as draft_chapters_count' => fn ($q) => $q->where('status', 'draft'),
                'chapters as revised_chapters_count' => fn ($q) => $q->where('status', 'revised'),
                'chapters as final_chapters_count' => fn ($q) => $q->where('status', 'final'),
            ])
            ->withSum('chapters', 'word_count')
            ->with('storylines:id,book_id,name')
            ->latest()
            ->get();

        return Inertia::render('books/index', [
            'books' => $books,
        ]);
    }

    public function store(StoreBookRequest $request): RedirectResponse
    {
        $book = Book::create($request->validated());

        return redirect()->route('books.import', $book);
    }

    public function skipImport(Book $book): RedirectResponse
    {
        $book->storylines()->create([
            'name' => 'Main',
            'type' => StorylineType::Main,
            'sort_order' => 0,
        ]);

        return redirect()->route('books.editor', $book);
    }

    public function import(Book $book): Response
    {
        $book->load('storylines:id,book_id,name');

        return Inertia::render('books/import', [
            'book' => $book,
        ]);
    }

    public function parse(ParseImportRequest $request, Book $book, DocumentParserFactory $factory): JsonResponse
    {
        $results = [];

        foreach ($request->validated('files') as $fileEntry) {
            $ext = $fileEntry['file']->getClientOriginalExtension();
            $parser = $factory->forExtension($ext);
            $parsed = $parser->parse($fileEntry['file']);

            $results[] = [
                'storyline_name' => $fileEntry['storyline_name'],
                'storyline_type' => $fileEntry['storyline_type'],
                'chapters' => $parsed['chapters'],
            ];
        }

        if ($request->boolean('merge_into_single_storyline') && count($results) > 1) {
            $mergedChapters = [];
            $number = 1;

            foreach ($results as $storyline) {
                foreach ($storyline['chapters'] as $chapter) {
                    $chapter['number'] = $number++;
                    $mergedChapters[] = $chapter;
                }
            }

            $results = [
                [
                    'storyline_name' => $results[0]['storyline_name'],
                    'storyline_type' => $results[0]['storyline_type'],
                    'chapters' => $mergedChapters,
                ],
            ];
        }

        return response()->json(['storylines' => $results]);
    }

    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        $book->update($request->validated());

        return redirect()->route('books.index');
    }

    public function destroy(Book $book): RedirectResponse
    {
        // Clean up chunk_embeddings (vec0 virtual table doesn't cascade)
        $chunkIds = Chunk::query()
            ->whereIn(
                'chapter_version_id',
                $book->chapters()
                    ->join('chapter_versions', 'chapters.id', '=', 'chapter_versions.chapter_id')
                    ->select('chapter_versions.id')
            )
            ->pluck('id');

        if ($chunkIds->isNotEmpty()) {
            DB::delete(
                'DELETE FROM chunk_embeddings WHERE chunk_id IN ('.implode(',', $chunkIds->all()).')'
            );
        }

        $book->delete();

        return redirect()->route('books.index');
    }

    public function duplicate(Book $book): RedirectResponse
    {
        DB::transaction(function () use ($book) {
            $newBook = $book->replicate([
                'writing_style',
                'story_bible',
                'prose_pass_rules',
            ]);
            $newBook->title = $book->title.' (Copy)';
            $newBook->save();

            /** @var array<int, int> */
            $storylineMap = [];
            foreach ($book->storylines as $storyline) {
                $newStoryline = $storyline->replicate();
                $newStoryline->book_id = $newBook->id;
                $newStoryline->save();
                $storylineMap[$storyline->id] = $newStoryline->id;
            }

            /** @var array<int, int> */
            $actMap = [];
            foreach ($book->acts as $act) {
                $newAct = $act->replicate();
                $newAct->book_id = $newBook->id;
                $newAct->save();
                $actMap[$act->id] = $newAct->id;
            }

            /** @var array<int, int> */
            $chapterMap = [];
            foreach ($book->chapters()->with(['versions', 'scenes'])->get() as $chapter) {
                $newChapter = $chapter->replicate();
                $newChapter->book_id = $newBook->id;
                $newChapter->storyline_id = $storylineMap[$chapter->storyline_id] ?? $chapter->storyline_id;
                $newChapter->act_id = isset($chapter->act_id) ? ($actMap[$chapter->act_id] ?? null) : null;
                $newChapter->pov_character_id = null; // Remap after characters are created
                $newChapter->save();
                $chapterMap[$chapter->id] = $newChapter->id;

                foreach ($chapter->versions as $version) {
                    $newVersion = $version->replicate();
                    $newVersion->chapter_id = $newChapter->id;
                    $newVersion->save();
                }

                foreach ($chapter->scenes as $scene) {
                    $newScene = $scene->replicate();
                    $newScene->chapter_id = $newChapter->id;
                    $newScene->save();
                }
            }

            /** @var array<int, int> */
            $characterMap = [];
            foreach ($book->characters as $character) {
                $newCharacter = $character->replicate();
                $newCharacter->book_id = $newBook->id;
                $newCharacter->first_appearance = isset($character->first_appearance)
                    ? ($chapterMap[$character->first_appearance] ?? null)
                    : null;
                $newCharacter->save();
                $characterMap[$character->id] = $newCharacter->id;
            }

            // Remap pov_character_id on chapters
            foreach ($book->chapters as $chapter) {
                if ($chapter->pov_character_id && isset($characterMap[$chapter->pov_character_id])) {
                    $newBook->chapters()
                        ->where('id', $chapterMap[$chapter->id])
                        ->update(['pov_character_id' => $characterMap[$chapter->pov_character_id]]);
                }
            }

            // Copy character_chapter pivot
            foreach ($book->chapters()->with('characters')->get() as $chapter) {
                if (! isset($chapterMap[$chapter->id])) {
                    continue;
                }
                foreach ($chapter->characters as $character) {
                    if (! isset($characterMap[$character->id])) {
                        continue;
                    }
                    DB::table('character_chapter')->insert([
                        'character_id' => $characterMap[$character->id],
                        'chapter_id' => $chapterMap[$chapter->id],
                        'role' => $character->pivot->role,
                        'notes' => $character->pivot->notes,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Copy plot points with remapped foreign keys
            foreach ($book->plotPoints as $plotPoint) {
                $newPlotPoint = $plotPoint->replicate();
                $newPlotPoint->book_id = $newBook->id;
                $newPlotPoint->act_id = isset($plotPoint->act_id)
                    ? ($actMap[$plotPoint->act_id] ?? null)
                    : null;
                $newPlotPoint->save();
            }
        });

        return redirect()->route('books.index');
    }

    public function confirmImport(ConfirmImportRequest $request, Book $book, NormalizationService $normalizer): RedirectResponse
    {
        $storylineOrder = 0;

        foreach ($request->validated('storylines') as $storylineData) {
            $storyline = $book->storylines()->create([
                'name' => $storylineData['name'],
                'type' => $storylineData['type'],
                'sort_order' => $storylineOrder++,
            ]);

            $chapterOrder = 0;

            foreach ($storylineData['chapters'] as $chapterData) {
                if (! $chapterData['included']) {
                    continue;
                }

                if (trim($chapterData['content'] ?? '') === '') {
                    continue;
                }

                $normalized = $normalizer->normalize($chapterData['content'], $book->language ?? 'en');

                $chapter = $storyline->chapters()->create([
                    'book_id' => $book->id,
                    'title' => $chapterData['title'],
                    'reader_order' => $chapterOrder++,
                    'status' => 'draft',
                    'word_count' => $chapterData['word_count'],
                ]);

                $chapter->versions()->create([
                    'version_number' => 1,
                    'content' => $normalized['content'],
                    'source' => VersionSource::Original,
                    'is_current' => true,
                ]);

                $chapter->scenes()->create([
                    'title' => 'Scene 1',
                    'content' => $normalized['content'],
                    'sort_order' => 0,
                    'word_count' => $chapterData['word_count'],
                ]);
            }
        }

        return redirect()->route('books.editor', $book);
    }
}
