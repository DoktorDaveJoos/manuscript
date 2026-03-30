<?php

use App\Services\Export\ContentPreparer;

it('preserves bold in chapter HTML', function () {
    $preparer = new ContentPreparer;
    $html = '<p>This is <strong>bold</strong> text.</p>';
    $result = $preparer->toChapterHtml($html);
    expect($result)->toContain('<strong>bold</strong>');
});

it('preserves italic in chapter HTML', function () {
    $preparer = new ContentPreparer;
    $html = '<p>This is <em>italic</em> text.</p>';
    $result = $preparer->toChapterHtml($html);
    expect($result)->toContain('<em>italic</em>');
});

it('preserves strikethrough in chapter HTML', function () {
    $preparer = new ContentPreparer;
    $html = '<p>This is <s>struck</s> text.</p>';
    $result = $preparer->toChapterHtml($html);
    expect($result)->toContain('<s>struck</s>');
});

it('preserves blockquotes in chapter HTML', function () {
    $preparer = new ContentPreparer;
    $html = '<blockquote><p>A quoted passage.</p></blockquote>';
    $result = $preparer->toChapterHtml($html);
    expect($result)->toContain('<blockquote>');
});

it('preserves bold in XHTML for EPUB', function () {
    $preparer = new ContentPreparer;
    $html = '<p>This is <strong>bold</strong> text.</p>';
    $result = $preparer->toXhtml($html);
    expect($result)->toContain('<strong>bold</strong>');
});

it('preserves italic in XHTML for EPUB', function () {
    $preparer = new ContentPreparer;
    $html = '<p>This is <em>italic</em> text.</p>';
    $result = $preparer->toXhtml($html);
    expect($result)->toContain('<em>italic</em>');
});

it('returns formatted segments for DOCX', function () {
    $preparer = new ContentPreparer;
    $html = '<p>This is <strong>bold</strong> and <em>italic</em>.</p>';
    $result = $preparer->toFormattedSegments($html);

    expect($result)->toBeArray();
    $types = collect($result)->pluck('type')->unique()->toArray();
    expect($types)->toContain('paragraph-start');
    expect($types)->toContain('text');

    $boldSegment = collect($result)->first(fn ($s) => ($s['text'] ?? '') === 'bold');
    expect($boldSegment['bold'])->toBeTrue();

    $italicSegment = collect($result)->first(fn ($s) => ($s['text'] ?? '') === 'italic');
    expect($italicSegment['italic'])->toBeTrue();
});

it('handles scene breaks in formatted segments', function () {
    $preparer = new ContentPreparer;
    $html = '<p>Before.</p><hr><p>After.</p>';
    $result = $preparer->toFormattedSegments($html);

    $types = collect($result)->pluck('type')->toArray();
    expect($types)->toContain('scene-break');
});

it('handles blockquotes in formatted segments as italic', function () {
    $preparer = new ContentPreparer;
    $html = '<blockquote><p>Quoted text.</p></blockquote>';
    $result = $preparer->toFormattedSegments($html);

    $quotedSegment = collect($result)->first(fn ($s) => ($s['text'] ?? '') === 'Quoted text.');
    expect($quotedSegment['italic'])->toBeTrue();
});
