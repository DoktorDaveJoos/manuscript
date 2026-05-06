<?php

use App\Ai\Support\BeatEntityScanner;

beforeEach(function () {
    $this->scanner = new BeatEntityScanner;
});

it('returns matches with case-insensitive word-boundary semantics', function () {
    $matches = $this->scanner->findReferenced(
        beatDescriptions: ['Maja boards the Voyager probe.', 'John watches from afar.'],
        entities: [
            ['id' => 1, 'name' => 'Maja'],
            ['id' => 2, 'name' => 'John'],
            ['id' => 3, 'name' => 'Voyager'],
        ],
    );

    expect($matches)->toHaveCount(3);
    expect(collect($matches)->pluck('id')->all())->toEqualCanonicalizing([1, 2, 3]);
});

it('does not match a name embedded inside a longer word', function () {
    $matches = $this->scanner->findReferenced(
        beatDescriptions: ['Johnson arrives at the dock.'],
        entities: [['id' => 1, 'name' => 'John']],
    );

    expect($matches)->toBeEmpty();
});

it('handles unicode word boundaries (German umlauts, compound words)', function () {
    $matches = $this->scanner->findReferenced(
        beatDescriptions: ['Der Apparat trifft auf die Gravitationslinse.'],
        entities: [
            ['id' => 1, 'name' => 'Apparat'],
            ['id' => 2, 'name' => 'Voyager'],
            ['id' => 3, 'name' => 'Gravitationslinse'],
        ],
    );

    expect(collect($matches)->pluck('id')->all())->toEqualCanonicalizing([1, 3]);
});

it('skips entities whose name is shorter than three characters', function () {
    $matches = $this->scanner->findReferenced(
        beatDescriptions: ['AI chats with Z.', 'Maja arrives.'],
        entities: [
            ['id' => 1, 'name' => 'AI'],
            ['id' => 2, 'name' => 'Z'],
            ['id' => 3, 'name' => 'Maja'],
        ],
    );

    expect(collect($matches)->pluck('id')->all())->toEqual([3]);
});

it('returns empty for empty inputs', function () {
    expect($this->scanner->findReferenced([], []))->toBeEmpty();
    expect($this->scanner->findReferenced(['Maja arrives.'], []))->toBeEmpty();
    expect($this->scanner->findReferenced([], [['id' => 1, 'name' => 'Maja']]))->toBeEmpty();
});

it('records which beat indices each entity was found in', function () {
    $matches = $this->scanner->findReferenced(
        beatDescriptions: [
            'Maja arrives at the lab.',
            'John reviews the file.',
            'Maja briefs John.',
        ],
        entities: [
            ['id' => 1, 'name' => 'Maja'],
            ['id' => 2, 'name' => 'John'],
        ],
    );

    $byId = collect($matches)->keyBy('id');
    expect($byId[1]['beats'])->toEqualCanonicalizing([0, 2]);
    expect($byId[2]['beats'])->toEqualCanonicalizing([1, 2]);
});

it('dedupes within the same beat (one entity, one beat = one entry per beat)', function () {
    $matches = $this->scanner->findReferenced(
        beatDescriptions: ['Maja meets Maja in the mirror. Maja smiles.'],
        entities: [['id' => 1, 'name' => 'Maja']],
    );

    expect($matches)->toHaveCount(1);
    expect($matches[0]['beats'])->toEqual([0]);
});
