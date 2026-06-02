<?php

use App\Enums\TrimSize;
use App\Services\Export\CoverOptions;
use App\Services\Export\CoverService;

beforeEach(function () {
    $this->service = app(CoverService::class);
});

it('renders the title, subtitle and author into the cover html', function () {
    $html = $this->service->renderHtml(CoverOptions::fromArray([
        'title' => 'The Silent Tide',
        'subtitle' => 'A Thriller',
        'author' => 'Jane Doe',
        'trim_size' => '13x19cm',
    ]));

    expect($html)
        ->toContain('The Silent Tide')
        ->toContain('A Thriller')
        ->toContain('Jane Doe')
        // White background, black text, Garamond title.
        ->toContain('#ffffff')
        ->toContain('#000000')
        ->toContain('cormorantgaramond')
        // Author and genre are set in a clean sans-serif, never italic.
        ->toContain('sourcesans3')
        ->not->toContain('font-style: italic');
});

it('omits the subtitle and author blocks when they are empty', function () {
    $html = $this->service->renderHtml(CoverOptions::fromArray([
        'title' => 'Just A Title',
    ]));

    // The CSS rules are always present; assert the rendered elements are not.
    expect($html)
        ->toContain('Just A Title')
        ->not->toContain('class="cover-subtitle"')
        ->not->toContain('class="cover-author"');
});

it('generates a valid single-page pdf', function () {
    $pdf = $this->service->generatePdfString(CoverOptions::fromArray([
        'title' => 'Cover Test',
        'author' => 'Author',
        'trim_size' => '13x19cm',
    ]));

    expect($pdf)->toStartWith('%PDF-');
    expect(strlen($pdf))->toBeGreaterThan(1000);
});

it('sizes the data format to the trim size plus bleed on every edge', function () {
    $options = CoverOptions::fromArray([
        'title' => 'X',
        'trim_size' => '13x19cm',
        'bleed' => 3,
    ]);

    // 130 x 190 trim + 3mm bleed per edge = 136 x 196.
    expect($options->dataDimensions())->toBe(['width' => 136.0, 'height' => 196.0]);
});

it('defaults to the 13x19cm paperback when no trim size is given', function () {
    $options = CoverOptions::fromArray(['title' => 'X']);

    expect($options->trim())->toBe(TrimSize::Novel13x19);
});

it('supports every export trim size', function (TrimSize $trim) {
    $options = CoverOptions::fromArray([
        'title' => 'Sized Cover',
        'trim_size' => $trim->value,
    ]);

    $expected = [
        'width' => $trim->dimensions()['width'] + 6,
        'height' => $trim->dimensions()['height'] + 6,
    ];

    expect($options->dataDimensions())->toEqual($expected);
    expect($this->service->generatePdfString($options))->toStartWith('%PDF-');
})->with(array_map(fn (TrimSize $t) => [$t], TrimSize::cases()));

it('sizes the wraparound to back + spine + front plus bleed (FlyerAlarm 13x19 spec)', function () {
    $options = CoverOptions::fromArray([
        'title' => 'X',
        'trim_size' => '13x19cm',
        'bleed' => 3,
        'spine_width' => 3.5,
    ]);

    // 2 × 130 trim + 3.5 spine + 2 × 3 bleed = 269.5 wide; 190 + 6 = 196 tall.
    expect($options->wraparoundDimensions())->toEqual(['width' => 269.5, 'height' => 196.0]);
});

it('defaults the spine to zero so the wraparound is just back + front + bleed', function () {
    $options = CoverOptions::fromArray(['title' => 'X', 'trim_size' => '13x19cm', 'bleed' => 3]);

    expect($options->spineWidth)->toBe(0.0);
    expect($options->wraparoundDimensions())->toEqual(['width' => 266.0, 'height' => 196.0]);
});

it('renders the blurb onto the back face but not the front face', function () {
    $options = CoverOptions::fromArray([
        'title' => 'The Silent Tide',
        'author' => 'Jane Doe',
        'blurb' => 'When the lighthouse goes dark, the village holds its breath.',
    ]);

    $front = $this->service->renderHtml($options, CoverService::FACE_FRONT);
    $back = $this->service->renderHtml($options, CoverService::FACE_BACK);

    expect($front)
        ->toContain('The Silent Tide')
        ->not->toContain('the village holds its breath');

    expect($back)
        ->toContain('class="cover-blurb"')
        ->toContain('the village holds its breath')
        ->not->toContain('The Silent Tide');
});

it('leaves the back face blank when there is no blurb', function () {
    $options = CoverOptions::fromArray(['title' => 'No Blurb Yet']);

    expect($this->service->renderHtml($options, CoverService::FACE_BACK))
        ->not->toContain('class="cover-blurb"');
});

it('lays the wraparound out as both panels — blurb on the back, title on the front', function () {
    $options = CoverOptions::fromArray([
        'title' => 'Wrap Test',
        'author' => 'Author',
        'trim_size' => '13x19cm',
        'spine_width' => 3.5,
        'blurb' => 'A back-cover hook that sells the book.',
    ]);

    $html = $this->service->renderHtml($options, CoverService::FACE_WRAPAROUND);

    expect($html)
        ->toContain('Wrap Test')
        ->toContain('A back-cover hook that sells the book.');

    // Blurb (back panel) is laid out to the left of the title (front panel).
    expect(strpos($html, 'A back-cover hook'))->toBeLessThan(strpos($html, 'Wrap Test'));
});

it('positions the wraparound panels absolutely instead of in a margin-dropping table', function () {
    // mPDF discards CSS margins on block elements nested inside <td>, which collapsed the
    // title and blurb to the top of each panel and made the printed jacket diverge from the
    // single-face preview. The jacket must lay the panels out with absolute positioning so
    // the partials' margins (title drop, blurb inset) are honoured.
    $options = CoverOptions::fromArray([
        'title' => 'Wrap Test',
        'author' => 'Author',
        'trim_size' => '13x19cm', // 130 x 190 trim
        'bleed' => 3,
        'safety' => 5,
        'spine_width' => 3.5,
        'blurb' => 'A back-cover hook that sells the book.',
    ]);

    $html = $this->service->renderHtml($options, CoverService::FACE_WRAPAROUND);

    expect($html)
        ->toContain('position: absolute')
        // The old table layout swallowed the inner margins — it must be gone.
        ->not->toContain('class="cover-wrap"')
        // Back panel safe area: bleed + safety = 8mm from the top/left page edge.
        ->toContain('top: 8mm; left: 8mm;')
        // Front panel safe area: bleed + trim width + spine + safety = 3 + 130 + 3.5 + 5.
        ->toContain('left: 141.5mm;')
        // The title still drops into the upper-middle: (190 - 2*5) * 0.34 = 61.2mm.
        ->toContain('margin-top: 61.2mm');
});

it('generates a wraparound pdf wider than the front-only pdf', function () {
    $options = CoverOptions::fromArray([
        'title' => 'Wrap Test',
        'author' => 'Author',
        'trim_size' => '13x19cm',
        'spine_width' => 3.5,
        'blurb' => 'A hook.',
    ]);

    expect($this->service->generatePdfString($options, CoverService::FACE_WRAPAROUND))->toStartWith('%PDF-');
    expect($options->wraparoundDimensions()['width'])
        ->toBeGreaterThan($options->dataDimensions()['width']);
});
