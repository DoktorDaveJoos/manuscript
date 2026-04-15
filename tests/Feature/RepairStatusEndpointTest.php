<?php

use App\Database\SqliteVecConnector;

beforeEach(function () {
    $this->markerPath = SqliteVecConnector::markerPath();
    @unlink($this->markerPath);
});

afterEach(function () {
    @unlink($this->markerPath);
});

test('repair-status returns idle when no marker file exists', function () {
    $this->getJson('/repair-status')
        ->assertOk()
        ->assertJson(['state' => 'idle']);
});

test('repair-status returns repairing when marker exists', function () {
    file_put_contents($this->markerPath, json_encode([
        'started_at' => '2026-04-14T12:00:00+00:00',
        'trigger' => 'PRAGMA quick_check failed: malformed',
    ]));

    $this->getJson('/repair-status')
        ->assertOk()
        ->assertJson([
            'state' => 'repairing',
            'started_at' => '2026-04-14T12:00:00+00:00',
        ]);
});

test('repair-status handles a malformed marker gracefully', function () {
    file_put_contents($this->markerPath, 'not valid json');

    $this->getJson('/repair-status')
        ->assertOk()
        ->assertJson(['state' => 'repairing'])
        ->assertJsonPath('started_at', null);
});

test('ready endpoint returns 200 on healthy database', function () {
    $this->getJson('/ready')
        ->assertOk()
        ->assertJson(['ready' => true]);
});
