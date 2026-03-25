<?php

use App\Enums\SceneBreakStyle;
use App\Services\Export\ContentPreparer;

it('renders asterisks scene break in HTML', function () {
    $preparer = new ContentPreparer;
    $html = '<p>Before.</p><hr><p>After.</p>';
    $result = $preparer->toChapterHtml($html, SceneBreakStyle::Asterisks);
    expect($result)->toContain('scene-break--asterisks');
});

it('renders fleuron scene break in HTML', function () {
    $preparer = new ContentPreparer;
    $html = '<p>Before.</p><hr><p>After.</p>';
    $result = $preparer->toChapterHtml($html, SceneBreakStyle::Fleuron);
    expect($result)->toContain('scene-break--fleuron');
    expect($result)->toContain('❧');
});

it('renders rule scene break in HTML', function () {
    $preparer = new ContentPreparer;
    $html = '<p>Before.</p><hr><p>After.</p>';
    $result = $preparer->toChapterHtml($html, SceneBreakStyle::Rule);
    expect($result)->toContain('scene-break--rule');
});

it('renders blank space scene break in HTML', function () {
    $preparer = new ContentPreparer;
    $html = '<p>Before.</p><hr><p>After.</p>';
    $result = $preparer->toChapterHtml($html, SceneBreakStyle::BlankSpace);
    expect($result)->toContain('scene-break--blank');
});

it('renders scene break in XHTML for EPUB', function () {
    $preparer = new ContentPreparer;
    $html = '<p>Before.</p><hr><p>After.</p>';
    $result = $preparer->toXhtml($html, SceneBreakStyle::Flourish);
    expect($result)->toContain('scene-break--flourish');
});

it('renders scene break in plain text', function () {
    $preparer = new ContentPreparer;
    $html = '<p>Before.</p><hr><p>After.</p>';
    $result = $preparer->toPlainText($html, SceneBreakStyle::Fleuron);
    expect($result)->toContain('❧');
});

it('defaults to asterisks when no style specified', function () {
    $preparer = new ContentPreparer;
    $html = '<p>Before.</p><hr><p>After.</p>';
    $result = $preparer->toChapterHtml($html);
    expect($result)->toContain('*');
});
