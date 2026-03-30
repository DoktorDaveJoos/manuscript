<?php

use App\Services\Export\ContentPreparer;

it('adds drop cap to plain paragraph', function () {
    $preparer = new ContentPreparer;
    $html = '<p>The morning was cold.</p>';
    $result = $preparer->addDropCap($html);

    expect($result)->toBe('<p><span class="drop-cap">T</span>he morning was cold.</p>');
});

it('adds drop cap with leading punctuation', function () {
    $preparer = new ContentPreparer;
    $html = '<p>"The morning was cold."</p>';
    $result = $preparer->addDropCap($html);

    expect($result)->toContain('<span class="drop-cap">"T</span>');
});

it('adds drop cap with curly quote', function () {
    $preparer = new ContentPreparer;
    $html = '<p>'."\u{201C}".'The morning was cold.'."\u{201D}".'</p>';
    $result = $preparer->addDropCap($html);

    expect($result)->toContain('class="drop-cap"');
});

it('adds drop cap inside em tag', function () {
    $preparer = new ContentPreparer;
    $html = '<p><em>The morning was cold.</em></p>';
    $result = $preparer->addDropCap($html);

    expect($result)->toBe('<p><em><span class="drop-cap">T</span>he morning was cold.</em></p>');
});

it('adds drop cap inside nested em and strong tags', function () {
    $preparer = new ContentPreparer;
    $html = '<p><em><strong>The morning was cold.</strong></em></p>';
    $result = $preparer->addDropCap($html);

    expect($result)->toBe('<p><em><strong><span class="drop-cap">T</span>he morning was cold.</strong></em></p>');
});

it('adds drop cap inside em with leading quote', function () {
    $preparer = new ContentPreparer;
    $html = '<p><em>'."\u{201C}".'The morning was cold.</em></p>';
    $result = $preparer->addDropCap($html);

    expect($result)->toContain('class="drop-cap"');
    expect($result)->toContain("\u{201C}T</span>");
});

it('skips drop cap when no letter found', function () {
    $preparer = new ContentPreparer;
    $html = '<p>   </p>';
    $result = $preparer->addDropCap($html);

    expect($result)->toBe('<p>   </p>');
});

it('only applies drop cap to first paragraph', function () {
    $preparer = new ContentPreparer;
    $html = '<p>First paragraph.</p><p>Second paragraph.</p>';
    $result = $preparer->addDropCap($html);

    expect($result)->toContain('<span class="drop-cap">F</span>');
    expect(substr_count($result, 'drop-cap'))->toBe(1);
});
