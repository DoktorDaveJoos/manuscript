<?php

use App\Ai\Agents\BookChatAgent;
use App\Ai\Agents\ChapterAnalyzer;
use App\Ai\Agents\EntityConsolidator;
use App\Ai\Agents\EntityExtractor;
use App\Ai\Agents\ManuscriptAnalyzer;
use App\Ai\Agents\NextChapterAdvisor;
use App\Ai\Agents\ProseReviser;
use App\Ai\Agents\StoryBibleBuilder;
use App\Ai\Agents\TextBeautifier;
use App\Enums\AiTaskCategory;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Storyline;

test('AiTaskCategory column returns correct column name', function () {
    expect(AiTaskCategory::Writing->column())->toBe('writing_model')
        ->and(AiTaskCategory::Analysis->column())->toBe('analysis_model')
        ->and(AiTaskCategory::Extraction->column())->toBe('extraction_model');
});

test('AiSetting modelForCategory returns null when not configured', function () {
    $setting = AiSetting::factory()->create();

    expect($setting->modelForCategory(AiTaskCategory::Writing))->toBeNull()
        ->and($setting->modelForCategory(AiTaskCategory::Analysis))->toBeNull()
        ->and($setting->modelForCategory(AiTaskCategory::Extraction))->toBeNull();
});

test('AiSetting modelForCategory returns configured model', function () {
    $setting = AiSetting::factory()->create([
        'writing_model' => 'claude-opus-4-6',
        'analysis_model' => 'claude-sonnet-4-6',
        'extraction_model' => 'claude-haiku-4-5-20251001',
    ]);

    expect($setting->modelForCategory(AiTaskCategory::Writing))->toBe('claude-opus-4-6')
        ->and($setting->modelForCategory(AiTaskCategory::Analysis))->toBe('claude-sonnet-4-6')
        ->and($setting->modelForCategory(AiTaskCategory::Extraction))->toBe('claude-haiku-4-5-20251001');
});

test('writing agents have Writing task category', function () {
    expect(ProseReviser::taskCategory())->toBe(AiTaskCategory::Writing)
        ->and(TextBeautifier::taskCategory())->toBe(AiTaskCategory::Writing);
});

test('analysis agents have Analysis task category', function () {
    expect(ChapterAnalyzer::taskCategory())->toBe(AiTaskCategory::Analysis)
        ->and(ManuscriptAnalyzer::taskCategory())->toBe(AiTaskCategory::Analysis)
        ->and(NextChapterAdvisor::taskCategory())->toBe(AiTaskCategory::Analysis)
        ->and(StoryBibleBuilder::taskCategory())->toBe(AiTaskCategory::Analysis)
        ->and(BookChatAgent::taskCategory())->toBe(AiTaskCategory::Analysis);
});

test('extraction agents have Extraction task category', function () {
    expect(EntityExtractor::taskCategory())->toBe(AiTaskCategory::Extraction)
        ->and(EntityConsolidator::taskCategory())->toBe(AiTaskCategory::Extraction);
});

test('agent model returns null when no override is configured', function () {
    AiSetting::factory()->create();
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $agent = new ProseReviser($book, $chapter);

    expect($agent->model())->toBeNull();
});

test('agent model returns override when configured', function () {
    AiSetting::factory()->create([
        'writing_model' => 'claude-opus-4-6',
    ]);
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $agent = new ProseReviser($book, $chapter);

    expect($agent->model())->toBe('claude-opus-4-6');
});

test('agent model returns null when no provider is active', function () {
    AiSetting::factory()->disabled()->create();
    $book = Book::factory()->create();

    $agent = new EntityExtractor($book);

    expect($agent->model())->toBeNull();
});
