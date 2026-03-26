<?php

use App\Services\Export\Templates\ClassicTemplate;
use App\Services\Export\Templates\ElegantTemplate;
use App\Services\Export\Templates\ModernTemplate;

describe('ModernTemplate chapter header', function () {
    it('renders number only, left-aligned', function () {
        $template = new ModernTemplate;

        $html = $template->chapterHeaderHtml(0, 'The Beginning');

        expect($html)
            ->toContain('id="chapter-0"')
            ->toContain('>1</p>')
            ->toContain('<h1>The Beginning</h1>')
            ->not->toContain('Chapter');
    });

    it('escapes HTML in title', function () {
        $template = new ModernTemplate;

        $html = $template->chapterHeaderHtml(0, 'Tom & Jerry\'s "Escape"');

        expect($html)->toContain('Tom &amp; Jerry&#039;s &quot;Escape&quot;');
    });

    it('uses 1-based numbering', function () {
        $template = new ModernTemplate;

        expect($template->chapterHeaderHtml(4, 'Test'))->toContain('>5</p>');
    });
});

describe('ClassicTemplate chapter header', function () {
    it('renders CHAPTER N label with title', function () {
        $template = new ClassicTemplate;

        $html = $template->chapterHeaderHtml(0, 'The Beginning');

        expect($html)
            ->toContain('id="chapter-0"')
            ->toContain('Chapter 1')
            ->toContain('<h1>The Beginning</h1>');
    });

    it('uses numeric chapter numbers', function () {
        $template = new ClassicTemplate;

        $html = $template->chapterHeaderHtml(20, 'Late Chapter');

        expect($html)->toContain('Chapter 21');
    });
});

describe('ElegantTemplate chapter header', function () {
    it('renders spelled-out chapter number in English', function () {
        $template = new ElegantTemplate;

        $html = $template->chapterHeaderHtml(0, 'The Beginning', 'en');

        expect($html)
            ->toContain('id="chapter-0"')
            ->toContain('Chapter One')
            ->toContain('<h1>The Beginning</h1>');
    });

    it('spells out numbers up to ninety-nine', function () {
        $template = new ElegantTemplate;

        expect($template->chapterHeaderHtml(0, 'T', 'en'))->toContain('Chapter One');
        expect($template->chapterHeaderHtml(9, 'T', 'en'))->toContain('Chapter Ten');
        expect($template->chapterHeaderHtml(11, 'T', 'en'))->toContain('Chapter Twelve');
        expect($template->chapterHeaderHtml(19, 'T', 'en'))->toContain('Chapter Twenty');
        expect($template->chapterHeaderHtml(20, 'T', 'en'))->toContain('Chapter Twenty-One');
        expect($template->chapterHeaderHtml(98, 'T', 'en'))->toContain('Chapter Ninety-Nine');
    });

    it('falls back to numerals for non-English locales', function () {
        $template = new ElegantTemplate;

        $html = $template->chapterHeaderHtml(0, 'Der Anfang', 'de');

        expect($html)
            ->toContain('Kapitel 1')
            ->not->toContain('One');
    });

    it('falls back to numerals for numbers over 99', function () {
        $template = new ElegantTemplate;

        $html = $template->chapterHeaderHtml(99, 'Century', 'en');

        expect($html)->toContain('Chapter 100');
    });

    it('defaults drop caps to true', function () {
        $template = new ElegantTemplate;

        expect($template->defaultDropCaps())->toBeTrue();
    });

    it('uses normal weight drop cap CSS', function () {
        $template = new ElegantTemplate;

        expect($template->dropCapCss())->toContain('font-weight: normal');
    });
});

describe('template design tokens', function () {
    it('Modern has restrained title size', function () {
        $tokens = (new ModernTemplate)->designTokens();

        expect($tokens['titleSizeEm'])->toBe(1.2);
        expect($tokens['titleWeight'])->toBe('normal');
        expect($tokens['chapterLabelSizeEm'])->toBe(1.0);
    });

    it('Classic has bumped title size', function () {
        $tokens = (new ClassicTemplate)->designTokens();

        expect($tokens['titleSizeEm'])->toBe(1.8);
    });

    it('Elegant keeps 2.0em title', function () {
        $tokens = (new ElegantTemplate)->designTokens();

        expect($tokens['titleSizeEm'])->toBe(2.0);
    });
});
