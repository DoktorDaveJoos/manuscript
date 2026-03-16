<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\UsesTaskCategoryModel;
use App\Ai\Contracts\BelongsToBook;
use App\Ai\Middleware\InjectProviderCredentials;
use App\Ai\Tools\SearchSimilarChunks;
use App\Enums\AiTaskCategory;
use App\Enums\PlotPointType;
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
#[Timeout(180)]
class ChapterAnalyzer implements Agent, BelongsToBook, HasMiddleware, HasStructuredOutput, HasTools
{
    use Promptable, UsesTaskCategoryModel;

    public static function taskCategory(): AiTaskCategory
    {
        return AiTaskCategory::Analysis;
    }

    public function __construct(
        protected Book $book,
        protected string $precedingContext = '',
    ) {}

    public function book(): Book
    {
        return $this->book;
    }

    public function instructions(): Stringable|string
    {
        $context = "You are a literary analyst performing a combined chapter analysis for '{$this->book->title}' by {$this->book->author}. The manuscript is written in {$this->book->language}.";

        $genreSnippet = $this->book->genreSnippet();
        if ($genreSnippet) {
            $context .= ' '.$genreSnippet;
        }

        if ($this->precedingContext) {
            $context .= "\n\nContext from preceding chapters:\n{$this->precedingContext}";
        }

        return <<<INSTRUCTIONS
        {$context}

        Analyze the provided chapter text and return all of the following:

        **Core analysis:**
        1. A concise 2-3 sentence summary of the chapter
        2. Key events that occurred
        3. Characters who appear in this chapter
        4. Plot points extracted from the chapter with their type and description

        **Conflict & tension:**
        5. tension_score (1-10): Rate the overall conflict intensity. 1=peaceful, 10=maximum conflict. Low scores are not bad — deliberate quiet chapters are essential for rhythm.
        6. micro_tension_score (1-10): Rate the line-level unease: conflicting emotions, unanswered questions, internal contradiction, social friction. This measures engagement even in quiet scenes (Maass's micro-tension concept).

        **Scene craft:**
        7. scene_purpose: What function does this chapter serve? One of: turning_point (major value change), revelation (new information reshapes understanding), deepening (character/relationship development), setup (establishing future events), resolution (resolving a thread), transition (moving between story elements).
        8. value_shift: What value changed? Describe concisely, e.g. "safety → danger", "trust → betrayal", "ignorance → awareness". If nothing meaningfully changed, return null.

        **Emotional arc:**
        9. emotional_state_open: Describe the POV character's dominant emotional state at the chapter's opening.
        10. emotional_state_close: Describe the POV character's dominant emotional state at the chapter's close.
        11. emotional_shift_magnitude (1-10): How much did the POV character's emotional state change? 1=barely, 10=completely transformed.

        **Hooks:**
        12. hook_score (1-10): How effectively the chapter ending compels continued reading.
        13. hook_type: 'cliffhanger' (unresolved danger/revelation), 'soft_hook' (intriguing question/emotional pull), 'closed' (satisfying conclusion that still moves story forward), or 'dead_end' (no forward momentum).
        14. hook_reasoning: Brief reasoning for the hook classification.
        15. entry_hook_score (1-10): How effectively does the chapter opening pull the reader in? 1=weak/confusing, 10=immediately compelling.

        **Pacing & prose:**
        16. pacing_feel: Assess the prose rhythm. One of: breakneck (rapid action, short sentences), brisk (forward momentum, good clip), measured (balanced, deliberate), languid (slow, contemplative, descriptive), static (little movement or progression).
        17. sensory_grounding (1-5): How many distinct senses are meaningfully engaged (sight, sound, touch, taste, smell)? Not just mentioned — actually used to ground the reader.
        18. information_delivery: How is new information revealed? One of: organic (through action/dialogue), mostly_organic, mixed, exposition_heavy, info_dump.

        Use the search tool to find related passages from other chapters when cross-referencing themes or plot threads.
        The book ID is {$this->book->id}. Use this when calling the search tool.

        Be precise and analytical. Score honestly — not every chapter needs high scores. Low tension or quiet pacing can be exactly right for a chapter's role in the story.
        INSTRUCTIONS;
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->required(),
            'key_events' => $schema->array()->items($schema->string())->required(),
            'characters_present' => $schema->array()->items($schema->string())->required(),
            'tension_score' => $schema->integer()->min(1)->max(10)->required(),
            'micro_tension_score' => $schema->integer()->min(1)->max(10)->required(),
            'scene_purpose' => $schema->string()->enum(['turning_point', 'revelation', 'deepening', 'setup', 'resolution', 'transition'])->required(),
            'value_shift' => $schema->string()->nullable()->required(),
            'emotional_state_open' => $schema->string()->required(),
            'emotional_state_close' => $schema->string()->required(),
            'emotional_shift_magnitude' => $schema->integer()->min(1)->max(10)->required(),
            'hook_score' => $schema->integer()->min(1)->max(10)->required(),
            'hook_type' => $schema->string()->enum(['cliffhanger', 'soft_hook', 'closed', 'dead_end'])->required(),
            'hook_reasoning' => $schema->string()->required(),
            'entry_hook_score' => $schema->integer()->min(1)->max(10)->required(),
            'pacing_feel' => $schema->string()->enum(['breakneck', 'brisk', 'measured', 'languid', 'static'])->required(),
            'sensory_grounding' => $schema->integer()->min(1)->max(5)->required(),
            'information_delivery' => $schema->string()->enum(['organic', 'mostly_organic', 'mixed', 'exposition_heavy', 'info_dump'])->required(),
            'plot_points' => $schema->array()->items(
                $schema->object([
                    'title' => $schema->string()->required(),
                    'description' => $schema->string()->required(),
                    'type' => $schema->string()->enum(array_column(PlotPointType::cases(), 'value'))->required(),
                ])->withoutAdditionalProperties()
            )->required(),
        ];
    }

    public function tools(): iterable
    {
        return [
            new SearchSimilarChunks,
        ];
    }

    public function middleware(): array
    {
        return [
            new InjectProviderCredentials,
        ];
    }
}
