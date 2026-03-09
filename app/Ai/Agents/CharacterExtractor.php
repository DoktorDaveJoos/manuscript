<?php

namespace App\Ai\Agents;

use App\Ai\Contracts\BelongsToBook;
use App\Ai\Middleware\InjectProviderCredentials;
use App\Ai\Tools\LookupExistingCharacters;
use App\Models\Book;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.2)]
#[Timeout(90)]
class CharacterExtractor implements Agent, BelongsToBook, HasMiddleware, HasStructuredOutput, HasTools
{
    use Promptable;

    public function __construct(protected Book $book) {}

    public function book(): Book
    {
        return $this->book;
    }

    public function instructions(): Stringable|string
    {
        return <<<INSTRUCTIONS
        You are a literary analyst extracting characters from manuscript text.
        Analyze the provided chapter text and identify all characters mentioned.
        For each character, determine:
        - Their full name as used in the text
        - Any aliases, nicknames, or alternative names
        - A brief description based on what is revealed in the text
        - Their role: 'protagonist' if they are a main character driving the action, 'supporting' if they play a significant secondary role, or 'mentioned' if they are only referenced

        Before extracting characters, use the lookup tool to check existing characters to avoid duplicates and match aliases.

        The manuscript '{$this->book->title}' is written in {$this->book->language}.
        Return character names as they appear in the text (respect the original language).
        INSTRUCTIONS;
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'characters' => $schema->array()->items(
                $schema->object([
                    'name' => $schema->string()->required(),
                    'aliases' => $schema->array()->items($schema->string())->required(),
                    'description' => $schema->string()->required(),
                    'role' => $schema->string()->enum(['protagonist', 'supporting', 'mentioned'])->required(),
                ])->withoutAdditionalProperties()
            )->required(),
        ];
    }

    public function tools(): iterable
    {
        return [
            new LookupExistingCharacters,
        ];
    }

    public function middleware(): array
    {
        return [
            new InjectProviderCredentials,
        ];
    }
}
