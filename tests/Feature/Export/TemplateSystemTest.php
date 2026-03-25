<?php

use App\Enums\FontPairing;
use App\Services\Export\FontService;

it('returns font data for classic serif pairing', function () {
    $service = new FontService;
    $data = $service->mPdfFontDataForPairing(FontPairing::ClassicSerif);

    expect($data)->toHaveKey('crimsonpro');
});

it('returns font data for modern mixed pairing', function () {
    $service = new FontService;
    $data = $service->mPdfFontDataForPairing(FontPairing::ModernMixed);

    expect($data)->toHaveKey('sourcesans3');
    expect($data)->toHaveKey('sourceserif4');
});

it('returns font data for elegant serif pairing', function () {
    $service = new FontService;
    $data = $service->mPdfFontDataForPairing(FontPairing::ElegantSerif);

    expect($data)->toHaveKey('cormorantgaramond');
    expect($data)->toHaveKey('crimsonpro');
});

it('generates epub font face css for a pairing', function () {
    $service = new FontService;
    $css = $service->epubFontFaceCssForPairing(FontPairing::ClassicSerif);

    expect($css)->toContain('@font-face');
    expect($css)->toContain('Crimson Pro');
});

it('returns epub font files for a pairing', function () {
    $service = new FontService;
    $files = $service->epubFontFilesForPairing(FontPairing::ClassicSerif);

    expect($files)->toBeArray();
    expect(count($files))->toBeGreaterThan(0);
    expect($files[0])->toHaveKeys(['path', 'filename']);
});

it('checks font availability for pairing', function () {
    $service = new FontService;
    // Fonts are downloaded, so this should be true
    expect($service->fontsAvailableForPairing(FontPairing::ClassicSerif))->toBeTrue();
});

it('returns font directories', function () {
    $service = new FontService;
    $dirs = $service->mPdfFontDirectories();

    expect($dirs)->toBeArray();
    expect($dirs[0])->toContain('resources/fonts');
});
