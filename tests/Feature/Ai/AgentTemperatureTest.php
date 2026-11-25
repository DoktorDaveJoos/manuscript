<?php

use App\Ai\Agents\BlurbAgent;
use App\Ai\Agents\BookChatAgent;
use App\Ai\Agents\ChapterAnalyzer;
use App\Ai\Agents\ContinueWritingAgent;
use App\Ai\Agents\EditorialChatAgent;
use App\Ai\Agents\EditorialNotesAgent;
use App\Ai\Agents\EditorialSummaryAgent;
use App\Ai\Agents\EditorialSynthesisAgent;
use App\Ai\Agents\ManuscriptAnalyzer;
use App\Ai\Agents\PlotCoachAgent;
use App\Ai\Agents\ProseReviser;
use App\Ai\Agents\RewriteSelectionAgent;
use App\Ai\Agents\SceneStructurer;
use App\Ai\Agents\WritingStyleExtractor;
use App\Enums\AnalysisType;
use App\Enums\EditorialSectionType;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\PlotCoachSession;
use App\Models\Storyline;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Gateway\TextGenerationOptions;

it('omits temperature on OpenAI for every AI agent so GPT-5 models never reject requests', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $agents = [
        new BlurbAgent($book),
        new BookChatAgent($book, $chapter),
        new ChapterAnalyzer($book),
        new ContinueWritingAgent($book, $chapter),
        new EditorialChatAgent($book, 'context'),
        new EditorialNotesAgent($book),
        new EditorialSummaryAgent($book, 'summaries'),
        new EditorialSynthesisAgent($book, EditorialSectionType::Plot, 'data'),
        new ManuscriptAnalyzer($book, AnalysisType::Plothole),
        new PlotCoachAgent($book, $session),
        new ProseReviser($book, $chapter),
        new RewriteSelectionAgent($book, $chapter, 'selected text'),
        new SceneStructurer($book, $chapter),
        new WritingStyleExtractor($book),
    ];

    config(['ai.default' => 'openai']);
    foreach ($agents as $agent) {
        $reflection = new ReflectionClass($agent);
        expect($reflection->getAttributes(Temperature::class))->toBeEmpty(get_class($agent).' must not use #[Temperature]');
        expect(TextGenerationOptions::forAgent($agent)->temperature)->toBeNull(get_class($agent).' must omit temperature on OpenAI');
    }

    config(['ai.default' => 'anthropic']);
    foreach ($agents as $agent) {
        expect(TextGenerationOptions::forAgent($agent)->temperature)->toBeFloat(get_class($agent).' should provide temperature on Anthropic');
    }
});
