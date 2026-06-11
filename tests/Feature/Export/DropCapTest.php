<?php

use App\Services\Export\ContentPreparer;

it('adds drop cap to plain paragraph', function () {
    $preparer = new ContentPreparer;
    $html = '<p>The morning was cold.</p>';
    $result = $preparer->addDropCap($html);

    expect($result)->toBe('<p class="drop-cap-paragraph"><span class="drop-cap">T</span><span class="drop-cap-phrase">he morning was</span> cold.</p>');
});

it('tags the opening paragraph so templates can keep its leading even', function () {
    $preparer = new ContentPreparer;
    $result = $preparer->addDropCap('<p>The morning was cold.</p>');

    expect($result)->toContain('<p class="drop-cap-paragraph">');
});

it('merges the paragraph tag class with an existing class attribute', function () {
    $preparer = new ContentPreparer;
    $result = $preparer->addDropCap('<p class="opening">The morning was cold.</p>');

    expect($result)->toContain('<p class="opening drop-cap-paragraph">');
});

it('wraps the rest of the opening phrase for small-caps styling', function () {
    $preparer = new ContentPreparer;
    $result = $preparer->addDropCap('<p>The morning was cold and grey.</p>');

    expect($result)->toContain('<span class="drop-cap">T</span><span class="drop-cap-phrase">he morning was</span> cold and grey.');
});

it('keeps the phrase span inside surrounding inline tags', function () {
    $preparer = new ContentPreparer;
    $result = $preparer->addDropCap('<p><em>The morning was cold.</em></p>');

    expect($result)->toBe('<p class="drop-cap-paragraph"><em><span class="drop-cap">T</span><span class="drop-cap-phrase">he morning was</span> cold.</em></p>');
});

it('handles single-word paragraphs', function () {
    $preparer = new ContentPreparer;
    $result = $preparer->addDropCap('<p>Stop.</p>');

    expect($result)->toContain('<span class="drop-cap">S</span><span class="drop-cap-phrase">top.</span>');
});

it('never includes markup in the phrase span', function () {
    $preparer = new ContentPreparer;
    $result = $preparer->addDropCap('<p>Go <em>now</em> please.</p>');

    expect($result)->toContain('<span class="drop-cap">G</span><span class="drop-cap-phrase">o</span> <em>now</em> please.');
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

    expect($result)->toContain('<em><span class="drop-cap">T</span>');
});

it('adds drop cap inside nested em and strong tags', function () {
    $preparer = new ContentPreparer;
    $html = '<p><em><strong>The morning was cold.</strong></em></p>';
    $result = $preparer->addDropCap($html);

    expect($result)->toContain('<em><strong><span class="drop-cap">T</span>');
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
    expect(substr_count($result, 'class="drop-cap"'))->toBe(1);
    expect($result)->toContain('<p>Second paragraph.</p>');
});
