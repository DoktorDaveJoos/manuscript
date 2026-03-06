<?php

test('returns a successful response', function () {
    $response = $this->get(route('books.index'));

    $response->assertOk();
});
