<?php

use App\Jobs\PrepareBookForAi;
use App\Models\AiPreparation;
use App\Models\AppSetting;
use App\Models\Book;
use App\Models\License;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    AppSetting::clearCache();
});

it('starts ai preparation and dispatches job', function () {
    Bus::fake();
    License::factory()->create();
    [$book] = createBookWithChapters(2);
    AppSetting::set('show_ai_features', true);

    $response = $this->postJson(route('books.ai.prepare', $book));

    $response->assertOk()
        ->assertJsonStructure(['id', 'status', 'book_id']);

    Bus::assertDispatched(PrepareBookForAi::class);
    expect(AiPreparation::where('book_id', $book->id)->where('status', 'pending')->exists())->toBeTrue();
});

it('returns 422 when no ai provider is configured', function () {
    License::factory()->create();
    $book = Book::factory()->create();
    AppSetting::set('show_ai_features', true);

    // No AiSetting created, so activeProvider() returns null
    $response = $this->postJson(route('books.ai.prepare', $book));

    $response->assertUnprocessable()
        ->assertJson(['message' => 'AI is not enabled or no API key configured.']);
});

it('returns preparation status', function () {
    License::factory()->create();
    [$book, $chapters, $preparation] = createBookWithChapters(2);
    $preparation->update(['status' => 'analyzing', 'processed_chapters' => 1, 'total_chapters' => 2]);

    $response = $this->getJson(route('books.ai.prepare.status', $book));

    $response->assertOk()
        ->assertJson([
            'status' => 'analyzing',
            'processed_chapters' => 1,
            'total_chapters' => 2,
        ]);
});

it('returns null when no preparation exists', function () {
    License::factory()->create();
    $book = Book::factory()->create();

    $response = $this->getJson(route('books.ai.prepare.status', $book));

    $response->assertOk()
        ->assertExactJson([]);
});

it('cancels existing preparation when starting new one', function () {
    Bus::fake();
    License::factory()->create();
    [$book] = createBookWithChapters(1);
    AppSetting::set('show_ai_features', true);

    // Create an existing in-progress preparation
    $existing = $book->aiPreparations()->create(['status' => 'analyzing']);

    $response = $this->postJson(route('books.ai.prepare', $book));

    $response->assertOk();
    expect($existing->fresh()->status)->toBe('failed');
    expect(AiPreparation::where('book_id', $book->id)->where('status', 'pending')->exists())->toBeTrue();
});

it('returns 422 when ai features are disabled', function () {
    License::factory()->create();
    [$book] = createBookWithChapters(1);
    AppSetting::set('show_ai_features', false);

    $response = $this->postJson(route('books.ai.prepare', $book));

    $response->assertUnprocessable()
        ->assertJson(['message' => 'AI is not enabled or no API key configured.']);
});

it('requires license to access preparation routes', function () {
    [$book] = createBookWithChapters(1);

    $this->postJson(route('books.ai.prepare', $book))
        ->assertForbidden();

    $this->getJson(route('books.ai.prepare.status', $book))
        ->assertForbidden();
});
