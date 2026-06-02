<?php

use App\Enums\PreparationStep;

it('maps semantic index to chunking and embedding phases', function () {
    expect(PreparationStep::SemanticIndex->phases())->toBe(['chunking', 'embedding']);
});

it('maps single-phase steps to their phase', function () {
    expect(PreparationStep::WritingStyle->phases())->toBe(['writing_style']);
    expect(PreparationStep::ChapterAnalysis->phases())->toBe(['chapter_analysis']);
    expect(PreparationStep::Wiki->phases())->toBe(['entity_extraction']);
    expect(PreparationStep::StoryBible->phases())->toBe(['story_bible']);
    expect(PreparationStep::Health->phases())->toBe(['health_analysis']);
});

it('requires chapter analysis for story bible and health', function () {
    expect(PreparationStep::StoryBible->requires())->toBe([PreparationStep::ChapterAnalysis]);
    expect(PreparationStep::Health->requires())->toBe([PreparationStep::ChapterAnalysis]);
});

it('has no prerequisites for foundational steps', function () {
    expect(PreparationStep::SemanticIndex->requires())->toBe([]);
    expect(PreparationStep::WritingStyle->requires())->toBe([]);
    expect(PreparationStep::ChapterAnalysis->requires())->toBe([]);
    expect(PreparationStep::Wiki->requires())->toBe([]);
});

it('counts total granular phases across selected steps', function () {
    expect(PreparationStep::totalPhasesFor(['semantic_index']))->toBe(2);
    expect(PreparationStep::totalPhasesFor(['chapter_analysis', 'wiki']))->toBe(2);
    expect(PreparationStep::totalPhasesFor(['semantic_index', 'writing_style', 'chapter_analysis', 'wiki', 'story_bible', 'health']))->toBe(7);
});

it('lists all step values', function () {
    expect(PreparationStep::values())->toBe([
        'semantic_index', 'writing_style', 'chapter_analysis', 'wiki', 'story_bible', 'health',
    ]);
});
