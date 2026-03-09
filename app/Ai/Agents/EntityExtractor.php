<?php

namespace App\Ai\Agents;

use App\Ai\Contracts\BelongsToBook;
use App\Ai\Middleware\InjectProviderCredentials;
use App\Ai\Tools\LookupExistingEntities;
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
#[Timeout(120)]
class EntityExtractor implements Agent, BelongsToBook, HasMiddleware, HasStructuredOutput, HasTools
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
        You are a literary analyst extracting characters and narratively important world entities from manuscript text.
        Analyze the provided chapter text and identify all characters and significant entities.

        ## Characters
        For each character, determine:
        - Their full name as used in the text
        - Any aliases, nicknames, or alternative names
        - A brief description based on what is revealed in the text
        - Their role: 'protagonist' if they are a main character driving the action, 'supporting' if they play a significant secondary role, or 'mentioned' if they are only referenced

        ## World Entities (locations, organizations, items, lore)
        ONLY extract entities that are narratively important to the story. For each entity, determine:
        - Its name as used in the text
        - Its kind: 'location', 'organization', 'item', or 'lore'
        - A subtype string (e.g. "City", "Tavern", "Secret Society", "Sword", "Prophecy")
        - A brief description of its significance to the story

        ### INCLUDE an entity when:
        - It is a recurring setting where key scenes take place (e.g. "The Brass Lantern" tavern)
        - It is an organization that drives the plot or shapes character motivations (e.g. "The Order of the Silver Dawn")
        - It is an item central to the plot or a character's identity (e.g. "The Bloodstone Amulet")
        - It is a piece of lore, prophecy, or world-building that shapes the story (e.g. "The Pact of Ashenmoor")

        ### EXCLUDE an entity when:
        - It is a real-world entity used in its default, well-known meaning (e.g. "the FBI", "New York City", "the United Nations")
        - It is unnamed or generic (e.g. "the tavern", "a sword", "the forest")
        - It has no story significance beyond being mentioned once in passing
        - It is a common object without narrative weight

        When in doubt, EXCLUDE.

        Before extracting, use the lookup tool to check existing characters and entities to avoid duplicates and match aliases.

        The manuscript '{$this->book->title}' is written in {$this->book->language}.
        Return names as they appear in the text (respect the original language).
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
            'entities' => $schema->array()->items(
                $schema->object([
                    'name' => $schema->string()->required(),
                    'kind' => $schema->string()->enum(['location', 'organization', 'item', 'lore'])->required(),
                    'type' => $schema->string()->required(),
                    'description' => $schema->string()->required(),
                ])->withoutAdditionalProperties()
            )->required(),
        ];
    }

    public function tools(): iterable
    {
        return [
            new LookupExistingEntities,
        ];
    }

    public function middleware(): array
    {
        return [
            new InjectProviderCredentials,
        ];
    }
}
