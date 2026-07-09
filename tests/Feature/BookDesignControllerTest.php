<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\DesignTemplate;
use App\Models\Scene;
use App\Services\Export\Templates\ClassicTemplate;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the book designer page', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->for($book)->create(['title' => 'Tod im Tech-Hub']);
    Scene::factory()->for($chapter)->create(['content' => '<p>Im Dämmerlicht…</p>']);
    DesignTemplate::factory()->create(['name' => 'My Custom']);

    $this->get(route('books.design', $book))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('books/design')
            ->has('book')
            ->has('builtInTemplates', 3)
            ->has('builtInTemplates.0.settings')
            ->has('customTemplates', 1)
            ->has('trimSizes')
            ->has('fontPairings')
            ->has('sceneBreakStyles')
            ->has('currentTemplate')
        );
});

it('creates a custom template', function () {
    $book = Book::factory()->create();

    $response = $this->postJson(route('books.design.templates.store', $book), [
        'name' => 'Classic (Custom)',
        'based_on' => 'classic',
        'settings' => (new ClassicTemplate)->designSettings(),
    ]);

    $response->assertCreated();
    expect(DesignTemplate::query()->count())->toBe(1);
});

it('updates a custom template', function () {
    $book = Book::factory()->create();
    $row = DesignTemplate::factory()->create();
    $settings = $row->settings;
    $settings['typography']['font_size'] = 13;

    $this->putJson(route('books.design.templates.update', [$book, $row]), [
        'name' => 'Renamed',
        'settings' => $settings,
    ])->assertOk();

    $row->refresh();
    expect($row->name)->toBe('Renamed')
        ->and($row->settings['typography']['font_size'])->toBe(13);
});

it('rejects an unknown based_on when creating a template', function () {
    $book = Book::factory()->create();

    $this->postJson(route('books.design.templates.store', $book), [
        'name' => 'Bad',
        'based_on' => 'weird',
        'settings' => (new ClassicTemplate)->designSettings(),
    ])->assertUnprocessable();
});

it('deletes a custom template and resets books referencing it', function () {
    $book = Book::factory()->create();
    $row = DesignTemplate::factory()->create(['based_on' => 'elegant']);
    $book->update(['export_settings' => ['template' => 'custom:'.$row->id]]);

    $this->deleteJson(route('books.design.templates.destroy', [$book, $row]))->assertOk();

    expect(DesignTemplate::query()->count())->toBe(0)
        ->and($book->fresh()->export_settings['template'])->toBe('elegant');
});

it('applies a template to the book without clobbering other export settings', function () {
    $book = Book::factory()->create(['export_settings' => ['format' => 'pdf', 'front_matter' => ['toc']]]);
    $row = DesignTemplate::factory()->create();

    $this->putJson(route('books.design.apply', $book), ['template' => 'custom:'.$row->id])->assertOk();

    $settings = $book->fresh()->export_settings;
    expect($settings['template'])->toBe('custom:'.$row->id)
        ->and($settings['front_matter'])->toBe(['toc'])
        ->and($settings['format'])->toBe('pdf');
});

it('rejects a custom trim size without explicit page dimensions', function () {
    $book = Book::factory()->create();
    $settings = (new ClassicTemplate)->designSettings();
    $settings['page']['trim_size'] = 'custom';
    // custom_width / custom_height intentionally left null

    $this->postJson(route('books.design.templates.store', $book), [
        'name' => 'Custom Trim',
        'based_on' => 'classic',
        'settings' => $settings,
    ])->assertUnprocessable();
});
