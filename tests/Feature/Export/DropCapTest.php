<?php

use App\Services\Export\ContentPreparer;

it('adds drop cap to first paragraph', function () {
    $preparer = new ContentPreparer;
    $html = '<p>The story begins here.</p><p>Second paragraph.</p>';
    $result = $preparer->addDropCap($html);
    expect($result)->toContain('<span class="drop-cap">T</span>');
    expect($result)->toContain('he story begins here.');
});

it('handles double-quote punctuation before first letter', function () {
    $preparer = new ContentPreparer;
    $html = '<p>"Hello," she said.</p>';
    $result = $preparer->addDropCap($html);
    expect($result)->toContain('<span class="drop-cap">"H</span>');
});

it('handles smart double-quote', function () {
    $preparer = new ContentPreparer;
    $html = '<p>'."\u{201C}".'Hello," she said.</p>';
    $result = $preparer->addDropCap($html);
    expect($result)->toContain('drop-cap');
});

it('handles single-quote punctuation', function () {
    $preparer = new ContentPreparer;
    $html = "<p>'Twas the night.</p>";
    $result = $preparer->addDropCap($html);
    expect($result)->toContain('drop-cap');
});

it('only affects first paragraph', function () {
    $preparer = new ContentPreparer;
    $html = '<p>First paragraph.</p><p>Second paragraph.</p>';
    $result = $preparer->addDropCap($html);
    expect(substr_count($result, 'drop-cap'))->toBe(1);
});

it('does not add drop cap to empty paragraphs', function () {
    $preparer = new ContentPreparer;
    $html = '<p></p><p>Real content.</p>';
    $result = $preparer->addDropCap($html);
    expect($result)->toContain('<span class="drop-cap">R</span>');
});
