<?php

use App\Ai\Agents\ContinueWritingAgent;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Storyline;

test('continue writing agent injects enabled generation-applicable rules', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $agent = new ContinueWritingAgent($book, $chapter);
    $instructions = (string) $agent->instructions();

    expect($instructions)->toContain('Style rules (apply while writing)')
        ->and($instructions)->toContain('Shorten overlong sentences')
        ->and($instructions)->toContain("reader's working memory")
        ->and($instructions)->toContain('Sentence variety')
        ->and($instructions)->toContain('Prose tightening');
});

test('continue writing agent omits revision-only rules even when enabled', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $agent = new ContinueWritingAgent($book, $chapter);
    $instructions = (string) $agent->instructions();

    expect($instructions)->not->toContain("Show, don't tell")
        ->and($instructions)->not->toContain('Dialogue tag cleanup');
});

test('continue writing agent omits rules the author has disabled', function () {
    $rules = Book::defaultProsePassRules();
    foreach ($rules as &$rule) {
        if ($rule['key'] === 'shorten_long_sentences') {
            $rule['enabled'] = false;
        }
    }
    unset($rule);

    $book = Book::factory()->create(['prose_pass_rules' => $rules]);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $agent = new ContinueWritingAgent($book, $chapter);
    $instructions = (string) $agent->instructions();

    expect($instructions)->not->toContain('Shorten overlong sentences')
        ->and($instructions)->toContain('Sentence variety');
});

test('continue writing agent omits style rules section when nothing applicable is enabled', function () {
    $rules = Book::defaultProsePassRules();
    foreach ($rules as &$rule) {
        $rule['enabled'] = false;
    }
    unset($rule);

    $book = Book::factory()->create(['prose_pass_rules' => $rules]);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $agent = new ContinueWritingAgent($book, $chapter);
    $instructions = (string) $agent->instructions();

    expect($instructions)->not->toContain('Style rules (apply while writing)');
});
