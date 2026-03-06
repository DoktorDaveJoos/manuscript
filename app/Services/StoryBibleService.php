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
        $response = $agent->prompt("Build a Story Bible from the following manuscript data:\n\n{$context}");

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

        if (! empty($bible['characters'])) {
            $sections[] = "\n### Characters";
            foreach ($bible['characters'] as $character) {
                $name = is_array($character) ? ($character['name'] ?? 'Unknown') : $character;
                $desc = is_array($character) ? ($character['description'] ?? $character['role'] ?? '') : '';
                $sections[] = "- {$name}: {$desc}";
            }
        }

        if (! empty($bible['themes'])) {
            $sections[] = "\n### Themes";
            foreach ($bible['themes'] as $theme) {
                $sections[] = '- '.(is_string($theme) ? $theme : ($theme['name'] ?? json_encode($theme)));
            }
        }

        if (! empty($bible['plot_outline'])) {
            $sections[] = "\n### Plot Outline";
            foreach ($bible['plot_outline'] as $beat) {
                $sections[] = '- '.(is_string($beat) ? $beat : ($beat['description'] ?? json_encode($beat)));
            }
        }

        if (! empty($bible['style_rules'])) {
            $sections[] = "\n### Style Rules";
            foreach ($bible['style_rules'] as $rule) {
                $sections[] = '- '.(is_string($rule) ? $rule : ($rule['description'] ?? json_encode($rule)));
            }
        }

        return implode("\n", $sections);
    }

    /**
     * Assemble context from the book's chapters, characters, and plot points.
     */
    private function assembleContext(Book $book): string
    {
        $sections = [];

        // Chapter summaries
        $chapters = $book->chapters()
            ->whereNotNull('summary')
            ->orderBy('reader_order')
            ->get(['id', 'title', 'reader_order', 'summary', 'tension_score', 'hook_score', 'hook_type']);

        if ($chapters->isNotEmpty()) {
            $sections[] = '## Chapter Summaries';
            foreach ($chapters as $chapter) {
                $sections[] = "Ch{$chapter->reader_order} — {$chapter->title}: {$chapter->summary}";
            }
        }

        // Characters
        $characters = $book->characters()->get();
        if ($characters->isNotEmpty()) {
            $sections[] = "\n## Characters";
            foreach ($characters as $character) {
                $aliases = $character->aliases ? ' (aliases: '.implode(', ', $character->aliases).')' : '';
                $sections[] = "- {$character->name}{$aliases}: {$character->description}";
            }
        }

        // Plot points
        $plotPoints = $book->plotPoints()->get();
        if ($plotPoints->isNotEmpty()) {
            $sections[] = "\n## Plot Points";
            foreach ($plotPoints as $point) {
                $sections[] = "- [{$point->type->value}/{$point->status->value}] {$point->title}: {$point->description}";
            }
        }

        // Writing style
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
