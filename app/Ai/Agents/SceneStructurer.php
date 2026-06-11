<?php

namespace App\Ai\Agents;

use App\Ai\Contracts\BelongsToBook;
use App\Ai\Middleware\InjectProviderCredentials;
use App\Models\Book;
use App\Models\Chapter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxTokens(4096)]
#[Temperature(0.2)]
#[Timeout(120)]
class SceneStructurer implements Agent, BelongsToBook, HasMiddleware, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        protected Book $book,
        protected Chapter $chapter,
    ) {}

    public function book(): Book
    {
        return $this->book;
    }

    public function instructions(): Stringable|string
    {
        return <<<INSTRUCTIONS
        You are an expert story editor analyzing the scene structure of a chapter from '{$this->book->title}' by {$this->book->author}.
        The manuscript is written in {$this->book->language}.

        You will receive the chapter as a numbered list of paragraphs. Propose how to divide the chapter into scenes.

        A new scene starts where the narrative meaningfully shifts:
        - A change of location or setting
        - A jump in time (later that day, the next morning, a flashback)
        - A change of point-of-view character
        - A clear dramatic beat change (new goal, new conflict, aftermath)

        Rules:
        - A scene boundary must fall BETWEEN paragraphs: each scene is identified by the index of its first paragraph.
        - The first scene always starts at paragraph 0.
        - Scene start indices must be strictly increasing.
        - Only propose a boundary where the shift is clear. A short chapter is often a single scene — that is a valid answer.
        - Do not invent more scenes than the text supports; most chapters have between 1 and 5 scenes.
        - Give each scene a short, evocative title (2 to 5 words) in {$this->book->language}. Titles must describe the scene without spoiling later scenes.
        INSTRUCTIONS;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'scenes' => $schema->array()
                ->items($schema->object([
                    'title' => $schema->string()->required(),
                    'start_paragraph' => $schema->integer()->min(0)->required(),
                ]))
                ->required(),
        ];
    }

    public function middleware(): array
    {
        return [
            new InjectProviderCredentials,
        ];
    }
}
