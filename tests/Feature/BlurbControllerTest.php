<?php

use App\Ai\Agents\BlurbAgent;
use App\Enums\AiProvider;
use App\Models\AiSetting;
use App\Models\Beat;
use App\Models\Book;
use App\Models\Character;
use App\Models\License;
use App\Models\PlotPoint;

beforeEach(function () {
    License::factory()->create();
});

test('blurb streams as SSE', function () {
    BlurbAgent::fake(['Ein Dorf am Rand der Welt hält den Atem an. ']);

    $book = Book::factory()->withAi()->create();

    $response = $this->post(route('books.ai.blurb', $book));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');

    $body = $response->streamedContent();
    expect($body)->toContain('[DONE]');

    $combined = '';
    foreach (preg_split('/\r?\n/', $body) as $line) {
        if (! str_starts_with($line, 'data: ')) {
            continue;
        }
        $payload = substr($line, 6);
        if ($payload === '[DONE]') {
            continue;
        }
        $decoded = json_decode($payload, true);
        $combined .= $decoded['delta'];
    }

    expect($combined)->toBe('Ein Dorf am Rand der Welt hält den Atem an. ');

    BlurbAgent::assertPrompted(fn ($prompt) => true);
});

test('blurb fails when no AI provider is configured', function () {
    $book = Book::factory()->create();
    AiSetting::factory()->withoutKey()->create([
        'provider' => AiProvider::Anthropic,
        'enabled' => true,
    ]);

    $this->postJson(route('books.ai.blurb', $book))->assertStatus(422);
});

test('agent instructions encode the blurb framework and the plot board', function () {
    $book = Book::factory()->withAi()->create([
        'title' => 'The Silent Tide',
        'author' => 'Jane Doe',
        'language' => 'German',
        'premise' => 'A lighthouse keeper guards a deadly secret.',
    ]);

    $plotPoint = PlotPoint::factory()->for($book)->create([
        'title' => 'The wreck',
        'description' => 'A ship founders on the rocks below the cliff.',
    ]);
    Beat::factory()->for($plotPoint)->create([
        'title' => 'A survivor washes ashore',
        'description' => 'She knows what happened that night.',
    ]);
    Character::factory()->for($book)->create([
        'name' => 'Elena',
        'description' => 'The keeper of the Ravenholm light.',
    ]);

    $agent = new BlurbAgent($book);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        // Plot board is fed in.
        ->toContain('The Silent Tide')
        ->toContain('Jane Doe')
        ->toContain('in German')
        ->toContain('A lighthouse keeper guards a deadly secret.')
        ->toContain('The wreck')
        ->toContain('A survivor washes ashore')
        ->toContain('Elena')
        // Researched framework is encoded.
        ->toContain('back-cover blurb')
        ->toContain('THE CENTRAL MYSTERY')
        ->toContain('200–260 words')
        ->toContain('third-person jacket voice')
        // Spoiler discipline is the headline rule.
        ->toContain('DO NOT SPOIL')
        ->toContain('WITHHOLDS')
        // No specific book is embedded as an example.
        ->not->toContain('STYLE REFERENCE')
        ->not->toContain('Maja');
});

test('blurb 404s for a missing book', function () {
    BlurbAgent::fake(['x']);

    $this->post('/books/999999/ai/blurb')->assertNotFound();
});
