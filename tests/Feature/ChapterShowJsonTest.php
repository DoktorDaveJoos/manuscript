<?php

use App\Models\Book;
use App\Models\Chapter;

it('returns chapter data as json', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->for($book)->create();

    $response = $this->getJson("/books/{$book->id}/chapters/{$chapter->id}/json");

    $response->assertOk()
        ->assertJsonStructure([
            'chapter' => ['id', 'title', 'scenes'],
            'versionCount',
            'prosePassRules',
            'proofreadingConfig',
            'customDictionary',
        ]);
});
