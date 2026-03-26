<?php

use App\Enums\EditorialPersona;

it('has a Lektor case', function () {
    expect(EditorialPersona::Lektor->value)->toBe('lektor');
});

it('returns a label for each persona', function () {
    expect(EditorialPersona::Lektor->label())->toBe('Lektor');
});

it('returns persona instructions that include key honesty phrases', function () {
    $instructions = EditorialPersona::Lektor->instructions();

    expect($instructions)
        ->toContain('serve the work, not the author\'s ego')
        ->toContain('Do not inflate scores')
        ->toContain('compliment sandwich');
});

it('returns score calibration text', function () {
    $calibration = EditorialPersona::Lektor->scoreCalibration();

    expect($calibration)
        ->toContain('55-65')
        ->toContain('86-95');
});

it('returns severity definitions', function () {
    $severity = EditorialPersona::Lektor->severityDefinitions();

    expect($severity)
        ->toContain('critical')
        ->toContain('warning')
        ->toContain('suggestion');
});

it('returns anti-pattern rules', function () {
    $rules = EditorialPersona::Lektor->antiPatternRules();

    expect($rules)
        ->toContain('DO NOT')
        ->toContain('hedge');
});

use App\Ai\Agents\ChapterAnalyzer;
use App\Ai\Agents\EditorialChatAgent;
use App\Ai\Agents\EditorialNotesAgent;
use App\Ai\Agents\EditorialSummaryAgent;
use App\Ai\Agents\EditorialSynthesisAgent;
use App\Enums\EditorialSectionType;
use App\Models\Book;

it('synthesis agent instructions include persona and calibration', function () {
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
        ->toContain('DO NOT use compliment sandwiches')
        ->toContain('critical: Structural issues');
});

it('summary agent instructions include persona and honesty rules', function () {
    $book = Book::factory()->create();
    $agent = new EditorialSummaryAgent(
        book: $book,
        sectionSummaries: 'test summaries',
    );

    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('serve the work, not the author\'s ego')
        ->toContain('DO NOT use compliment sandwiches')
        ->not->toContain('Be balanced and constructive');
});

it('notes agent instructions lead with issues before strengths', function () {
    $book = Book::factory()->create();
    $agent = new EditorialNotesAgent(book: $book);

    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('serve the work, not the author\'s ego')
        ->toContain('lead with what needs the author\'s attention')
        ->not->toContain('highlight what works, what needs attention');
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
        ->toContain('not trying to win an argument')
        ->not->toContain('encouraging')
        ->not->toContain('respecting their creative vision');
});

it('chapter analyzer instructions include persona', function () {
    $book = Book::factory()->create();
    $agent = new ChapterAnalyzer(book: $book);

    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('serve the work, not the author\'s ego');
});
