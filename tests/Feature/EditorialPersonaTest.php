<?php

use App\Enums\EditorialPersona;

it('has a Lektor case', function () {
    expect(EditorialPersona::Lektor->value)->toBe('lektor');
});

it('returns a label for each persona', function () {
    expect(EditorialPersona::Lektor->label())->toBe('Lektor');
});

it('returns persona instructions that balance honesty with encouragement', function () {
    $instructions = EditorialPersona::Lektor->instructions();

    expect($instructions)
        ->toContain('serve the work, not the author\'s ego')
        ->toContain('invent praise')
        ->toContain('concrete path')
        ->toContain('what to protect')
        ->toContain('not as verdicts on the author\'s ability');
});

it('returns score calibration text', function () {
    $calibration = EditorialPersona::Lektor->scoreCalibration();

    expect($calibration)
        ->toContain('55-65')
        ->toContain('86-95')
        ->toContain('Do not inflate scores')
        ->toContain('never a prognosis');
});

it('returns severity definitions', function () {
    $severity = EditorialPersona::Lektor->severityDefinitions();

    expect($severity)
        ->toContain('critical')
        ->toContain('warning')
        ->toContain('suggestion')
        ->toContain('recommendation the author can act on');
});

it('returns anti-pattern rules covering both dishonest and discouraging patterns', function () {
    $rules = EditorialPersona::Lektor->antiPatternRules();

    expect($rules)
        ->toContain('DO NOT')
        ->toContain('hedge')
        ->toContain('DO NOT invent strengths')
        ->toContain('DO NOT state a problem without a concrete way to address it')
        ->toContain('DO NOT catastrophize')
        ->toContain('Critique the draft, never the writer')
        ->toContain('DO NOT skip genuine strengths');
});

use App\Ai\Agents\ChapterAnalyzer;
use App\Ai\Agents\EditorialChatAgent;
use App\Ai\Agents\EditorialNotesAgent;
use App\Ai\Agents\EditorialSummaryAgent;
use App\Ai\Agents\EditorialSynthesisAgent;
use App\Enums\EditorialSectionType;
use App\Models\Book;

it('synthesis agent instructions include persona, calibration, and strengths requirements', function () {
    $book = Book::factory()->create();
    $agent = new EditorialSynthesisAgent(
        book: $book,
        sectionType: EditorialSectionType::Plot,
        aggregatedData: 'test data',
    );

    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('serve the work, not the author\'s ego')
        ->toContain('55-65')
        ->toContain('Genuine strengths in this dimension')
        ->toContain('Never invent strengths to fill a quota')
        ->toContain('critical: Structural issues');
});

it('summary agent instructions require an encouraging but honest executive summary', function () {
    $book = Book::factory()->create();
    $agent = new EditorialSummaryAgent(
        book: $book,
        sectionSummaries: 'test summaries',
    );

    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('serve the work, not the author\'s ego')
        ->toContain('Open with what genuinely works')
        ->toContain('important problems plainly')
        ->toContain('no illusions about what the revision involves')
        ->toContain('Do not invent strengths to fill a quota')
        ->not->toContain('Be balanced and constructive');
});

it('notes agent instructions demand passage-anchored, actionable chapter notes', function () {
    $book = Book::factory()->create();
    $agent = new EditorialNotesAgent(book: $book);

    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('serve the work, not the author\'s ego')
        ->toContain('Anchor every observation in the text')
        ->toContain('concrete revision move')
        ->toContain('genuinely works and why it works')
        ->toContain('single highest-impact focus')
        ->toContain('A note that could be pasted under any chapter is a failed note');
});

it('chat agent instructions are direct and handle disagreement properly', function () {
    $book = Book::factory()->create();
    $agent = new EditorialChatAgent(
        book: $book,
        editorialContext: 'test context',
    );

    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('re-examine the evidence')
        ->toContain('not trying to win an argument');
});

it('chapter analyzer instructions include persona', function () {
    $book = Book::factory()->create();
    $agent = new ChapterAnalyzer(book: $book);

    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('serve the work, not the author\'s ego');
});

use App\Ai\Agents\ManuscriptAnalyzer;
use App\Enums\AnalysisType;

it('manuscript analyzer instructions include persona and anti-patterns', function () {
    $book = Book::factory()->create();
    $agent = new ManuscriptAnalyzer(book: $book, analysisType: AnalysisType::Plothole);

    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('serve the work, not the author\'s ego')
        ->toContain('DO NOT invent strengths');
});
