<?php

namespace App\Ai\Tools;

use App\Models\Book;
use App\Services\StoryBibleService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RetrieveManuscriptContext implements Tool
{
    public function description(): Stringable|string
    {
        return 'Retrieves manuscript context including characters, chapter summaries, plot points, story bible, and active chapter text for a given book.';
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'book_id' => $schema->integer()->required(),
            'chapter_id' => $schema->integer(),
            'include_characters' => $schema->boolean(),
            'include_plot_points' => $schema->boolean(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $book = Book::query()->findOrFail($request['book_id']);
        $sections = [];

        $sections[] = "Book: {$book->title} by {$book->author} (Language: {$book->language})";

        // Include Story Bible if available
        if ($book->story_bible) {
            $storyBibleService = app(StoryBibleService::class);
            $bibleContext = $storyBibleService->getContext($book);
            if ($bibleContext) {
                $sections[] = "\n".$bibleContext;
            }
        }

        if ($request['include_characters'] ?? true) {
            $characters = $book->characters()->get();
            if ($characters->isNotEmpty()) {
                $sections[] = "\n## Characters";
                foreach ($characters as $character) {
                    $aliases = $character->aliases ? ' (aliases: '.implode(', ', $character->aliases).')' : '';
                    $sections[] = "- {$character->name}{$aliases}: {$character->description}";
                }
            }
        }

        if ($request['include_plot_points'] ?? true) {
            $plotPoints = $book->plotPoints()->get();
            if ($plotPoints->isNotEmpty()) {
                $sections[] = "\n## Plot Points";
                foreach ($plotPoints as $point) {
                    $sections[] = "- [{$point->status->value}] {$point->title}: {$point->description}";
                }
            }
        }

        $chapters = $book->chapters()
            ->with('currentVersion')
            ->orderBy('reader_order')
            ->get();

        if ($chapters->isNotEmpty()) {
            $sections[] = "\n## Chapter Summaries";
            foreach ($chapters as $chapter) {
                $wordCount = $chapter->word_count;
                $summary = $chapter->summary ? " — {$chapter->summary}" : '';
                $hookInfo = $chapter->hook_score ? " [hook:{$chapter->hook_score}/10 ({$chapter->hook_type})]" : '';
                $tensionInfo = $chapter->tension_score ? " [tension:{$chapter->tension_score}/10]" : '';
                $sections[] = "- Ch{$chapter->reader_order}: {$chapter->title} ({$wordCount} words, {$chapter->status->value}){$tensionInfo}{$hookInfo}{$summary}";
            }
        }

        if ($request['chapter_id'] ?? null) {
            $chapter = $book->chapters()
                ->with('currentVersion')
                ->find($request['chapter_id']);

            if ($chapter?->currentVersion) {
                $sections[] = "\n## Active Chapter Text: {$chapter->title}";
                $sections[] = $chapter->currentVersion->content;
            }
        }

        return implode("\n", $sections);
    }
}
