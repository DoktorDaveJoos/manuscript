<?php

use App\Jobs\Editorial\RefreshWritingStyleJob;
use App\Services\WritingStyleService;

use function Pest\Laravel\mock;

test('RefreshWritingStyleJob extracts and stores the writing style on the book', function () {
    [$book, $chapters] = createBookWithChaptersForEditorial(1);
    $chapters[0]->currentVersion()->update([
        'content' => '<p>'.trim(str_repeat('prose ', 400)).'</p>',
    ]);

    mock(WritingStyleService::class)
        ->shouldReceive('extract')
        ->once()
        ->andReturn(['tone' => 'wry and melancholic']);

    (new RefreshWritingStyleJob($book))->handle(app(WritingStyleService::class));

    expect($book->refresh()->writing_style)->toBe(['tone' => 'wry and melancholic']);
});

test('RefreshWritingStyleJob skips extraction below the minimum sample size', function () {
    [$book, $chapters] = createBookWithChaptersForEditorial(1);
    $chapters[0]->currentVersion()->update([
        'content' => '<p>'.trim(str_repeat('thin ', 100)).'</p>',
    ]);

    mock(WritingStyleService::class)->shouldNotReceive('extract');

    (new RefreshWritingStyleJob($book))->handle(app(WritingStyleService::class));
});

test('RefreshWritingStyleJob skips extraction when the book has no prose', function () {
    [$book, $chapters] = createBookWithChaptersForEditorial(1);
    $chapters[0]->currentVersion()->update(['content' => null]);

    $styleBefore = $book->writing_style;

    mock(WritingStyleService::class)->shouldNotReceive('extract');

    (new RefreshWritingStyleJob($book))->handle(app(WritingStyleService::class));

    expect($book->refresh()->writing_style)->toBe($styleBefore);
});
