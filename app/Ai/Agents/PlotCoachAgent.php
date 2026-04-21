<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\UsesTaskCategoryModel;
use App\Ai\Contracts\BelongsToBook;
use App\Ai\Middleware\InjectProviderCredentials;
use App\Ai\Tools\LookupExistingEntities;
use App\Ai\Tools\Plot\GetPlotBoardState;
use App\Ai\Tools\RetrieveManuscriptContext;
use App\Enums\AiTaskCategory;
use App\Enums\PlotCoachStage;
use App\Models\Book;
use App\Models\PlotCoachSession;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.6)]
#[Timeout(180)]
class PlotCoachAgent implements Agent, BelongsToBook, Conversational, HasMiddleware, HasTools
{
    use Promptable, RemembersConversations, UsesTaskCategoryModel;

    public static function taskCategory(): AiTaskCategory
    {
        return AiTaskCategory::Analysis;
    }

    public function __construct(
        protected Book $book,
        protected PlotCoachSession $session,
    ) {}

    public function book(): Book
    {
        return $this->book;
    }

    public function session(): PlotCoachSession
    {
        return $this->session;
    }

    public function instructions(): Stringable|string
    {
        $persona = $this->persona();
        $stageGuidance = $this->stageGuidance();
        $state = $this->stateBlock();

        return trim($persona."\n\n".$stageGuidance."\n\n".$state);
    }

    public function tools(): iterable
    {
        return [
            new GetPlotBoardState,
            new RetrieveManuscriptContext,
            new LookupExistingEntities,
        ];
    }

    public function middleware(): array
    {
        return [
            new InjectProviderCredentials,
        ];
    }

    private function persona(): string
    {
        $title = $this->book->title;
        $author = $this->book->author;

        return <<<PERSONA
        You are an editorial plot coach working with the author of '{$title}' by {$author}. You're not a helper or an assistant — you're a working collaborator who has read a lot of books and has opinions.

        Voice rules:
        - Short sentences. Fewer words.
        - Ask real questions with curiosity, not to perform helpfulness.
        - Push back when something feels generic. "Everyone has a dead father" is fine to say.
        - Never say "As an AI" or "I can help you with that" or "Great question".
        - Match the user's energy. If they're exploring, explore with them. If they're pinning something down, pin it down.

        Discipline rules:
        - Propose a batch only when (a) the user has just agreed to something concrete, (b) they ask, or (c) the session is about to end. Never mid-riff. Never reflexively "want me to save that?" every turn.
        - Micro-commits are fine — one character, one beat, one storyline. Low friction, one line.
        - Do not re-summarize the structured state unsolicited. The user doesn't need the recap.
        - Tools are for fetching state and (later) applying writes. Call them when useful, not to pad turns.
        PERSONA;
    }

    private function stageGuidance(): string
    {
        return match ($this->session->stage) {
            PlotCoachStage::Intake => $this->intakeGuidance(),
            PlotCoachStage::Structure,
            PlotCoachStage::Plotting,
            PlotCoachStage::Entities,
            PlotCoachStage::Refinement,
            PlotCoachStage::Complete => '',
        };
    }

    private function intakeGuidance(): string
    {
        $genre = $this->book->genre?->label() ?? '(not set)';
        $targetLength = $this->book->target_word_count
            ? (string) $this->book->target_word_count
            : '(not set)';
        $premise = $this->book->premise ?: '(not set)';

        return <<<INTAKE
        Current stage: Intake.

        We need to pin down before plotting starts:
        1. Genre — already set to '{$genre}'. Confirm if the user hasn't affirmed it yet.
        2. Target length — already set to {$targetLength} words. Confirm.
        3. Premise — short one-sentence hook. (Stored on book.premise — currently: '{$premise}')
        4. Protagonist sketch — name, core want, core wound.
        5. Central conflict — internal vs external, stakes.
        6. Coaching mode — ask once: "Pitch freely" (suggestive) or "Keep it structural" (guided).

        Absorb multiple answers in one user message if they give them. Don't re-ask what's already known. Don't ask them in rigid order — conversational. When 1–5 are satisfied, propose 2–3 candidate structures (later phase).
        INTAKE;
    }

    private function stateBlock(): string
    {
        $payload = [
            'decisions' => $this->session->decisions ?? [],
            'coaching_mode' => $this->session->coaching_mode?->value,
            'stage' => $this->session->stage->value,
        ];

        return "## Session state\n".json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
