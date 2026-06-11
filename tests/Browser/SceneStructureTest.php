<?php

use App\Ai\Agents\SceneStructurer;
use App\Enums\VersionSource;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\License;
use App\Models\Scene;
use App\Models\Storyline;

beforeEach(fn () => License::factory()->create());

it('suggests a scene structure and applies it on accept', function () {
    SceneStructurer::fake(fn () => ['scenes' => [
        ['title' => 'The Arrival', 'start_paragraph' => 0],
        ['title' => 'Old Debts', 'start_paragraph' => 2],
    ]]);

    $content = '<p>Rain hammered the station roof as Elena stepped off the train.</p>'
        .'<p>She found the platform empty and the ticket office dark.</p>'
        .'<p>Across town, Marlowe poured a second drink and watched the door.</p>'
        .'<p>He had been waiting ten years for this night to arrive.</p>';

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create([
        'reader_order' => 1,
        'title' => 'Chapter 1',
    ]);
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => $content,
    ]);
    Scene::factory()->for($chapter)->create([
        'title' => 'Scene 1',
        'content' => $content,
        'sort_order' => 0,
        'word_count' => 40,
    ]);
    $chapter->refreshContentHash();

    $page = visit("/books/{$book->id}/editor?panes={$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-access-bar="ai"]')
        ->assertSee('Structure Scenes')
        ->click('Structure Scenes')
        ->wait(2)
        ->assertSee('Suggested Scenes')
        ->assertSee('The Arrival')
        ->assertSee('Old Debts')
        ->click('Accept Structure')
        ->wait(2);

    $page->assertNoJavaScriptErrors();

    $chapter->refresh()->load('scenes');
    expect($chapter->scenes)->toHaveCount(2)
        ->and($chapter->scenes[0]->title)->toBe('The Arrival')
        ->and($chapter->scenes[0]->content)->toContain('Rain hammered')
        ->and($chapter->scenes[1]->title)->toBe('Old Debts')
        ->and($chapter->scenes[1]->content)->toContain('Marlowe');

    $version = $chapter->versions()->where('is_current', true)->first();
    expect($version->source)->toBe(VersionSource::SceneStructure);
});

it('keeps the chapter untouched when the proposal is dismissed', function () {
    SceneStructurer::fake(fn () => ['scenes' => [
        ['title' => 'The Arrival', 'start_paragraph' => 0],
        ['title' => 'Old Debts', 'start_paragraph' => 1],
    ]]);

    $content = '<p>First paragraph of the chapter.</p><p>Second paragraph of the chapter.</p>';

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create([
        'reader_order' => 1,
        'title' => 'Chapter 1',
    ]);
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'content' => $content,
    ]);
    Scene::factory()->for($chapter)->create([
        'title' => 'Scene 1',
        'content' => $content,
        'sort_order' => 0,
        'word_count' => 10,
    ]);
    $chapter->refreshContentHash();

    $page = visit("/books/{$book->id}/editor?panes={$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-access-bar="ai"]')
        ->click('Structure Scenes')
        ->wait(2)
        ->assertSee('Suggested Scenes')
        ->click('Cancel')
        ->wait(1);

    $page->assertNoJavaScriptErrors()
        ->assertDontSee('Suggested Scenes');

    $chapter->refresh()->load('scenes');
    expect($chapter->scenes)->toHaveCount(1)
        ->and($chapter->scenes[0]->title)->toBe('Scene 1');

    $version = $chapter->versions()->where('is_current', true)->first();
    expect($version->version_number)->toBe(1);
});
