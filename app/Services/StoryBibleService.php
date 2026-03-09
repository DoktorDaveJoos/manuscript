<?php

namespace App\Services;

use App\Ai\Agents\StoryBibleBuilder;
use App\Models\Book;

class StoryBibleService
{
    /**
     * Build a Story Bible from all gathered chapter data and store it on the book.
     *
     * @return array<string, mixed>
     */
    public function build(Book $book): array
    {
        $context = $this->assembleContext($book);

        $agent = new StoryBibleBuilder($book);
        $response = $agent->prompt("Extract themes, style rules, genre rules, and timeline from the following manuscript data:\n\n{$context}");

        $storyBible = $response->toArray();
        $book->update(['story_bible' => $storyBible]);

        return $storyBible;
    }

    /**
     * Format the Story Bible into prompt-friendly text for other agents.
     */
    public function getContext(Book $book): string
    {
        $bible = $book->story_bible;

        if (empty($bible)) {
            return '';
        }

        $sections = ["## Story Bible for '{$book->title}'"];

        foreach (['themes' => 'Themes', 'style_rules' => 'Style Rules', 'genre_rules' => 'Genre Rules', 'timeline' => 'Timeline'] as $key => $heading) {
            if (! empty($bible[$key])) {
                $sections[] = "\n### {$heading}";
                foreach ($bible[$key] as $item) {
                    $sections[] = '- '.(is_string($item) ? $item : json_encode($item));
                }
            }
        }

        return implode("\n", $sections);
    }

    /**
     * Assemble context from chapter summaries and writing style for the StoryBibleBuilder agent.
     */
    private function assembleContext(Book $book): string
    {
        $sections = [];

        $chapters = $book->chapters()
            ->whereNotNull('summary')
            ->orderBy('reader_order')
            ->get(['id', 'title', 'reader_order', 'summary']);

        if ($chapters->isNotEmpty()) {
            $sections[] = '## Chapter Summaries';
            foreach ($chapters as $chapter) {
                $sections[] = "Ch{$chapter->reader_order} — {$chapter->title}: {$chapter->summary}";
            }
        }

        if ($book->writing_style) {
            $sections[] = "\n## Writing Style";
            foreach ($book->writing_style as $key => $value) {
                if (is_array($value)) {
                    $sections[] = "- {$key}: ".implode(', ', $value);
                } else {
                    $sections[] = "- {$key}: {$value}";
                }
            }
        }

        return implode("\n", $sections);
    }
}
