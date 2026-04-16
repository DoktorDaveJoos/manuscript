<?php

use App\Polyfills\XmlWriter;
use PhpOffice\PhpWord\PhpWord;

it('writes a simple element with attributes and text', function () {
    $w = new XmlWriter;
    $w->openMemory();
    $w->startDocument('1.0', 'UTF-8', 'yes');
    $w->startElement('greeting');
    $w->writeAttribute('lang', 'en');
    $w->text('Hello & welcome');
    $w->endElement();

    expect($w->outputMemory())->toBe(
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><greeting lang="en">Hello &amp; welcome</greeting>'
    );
});

it('self-closes empty elements', function () {
    $w = new XmlWriter;
    $w->openMemory();
    $w->startElement('br');
    $w->endElement();

    expect($w->outputMemory())->toBe('<br/>');
});

it('escapes special characters in attributes', function () {
    $w = new XmlWriter;
    $w->openMemory();
    $w->startElement('a');
    $w->writeAttribute('href', 'http://x.test/?q=1&r=2');
    $w->writeAttribute('title', 'He said "hi"');
    $w->endElement();

    expect($w->outputMemory())->toBe(
        '<a href="http://x.test/?q=1&amp;r=2" title="He said &quot;hi&quot;"/>'
    );
});

it('escapes special characters in text content', function () {
    $w = new XmlWriter;
    $w->openMemory();
    $w->writeElement('p', 'a < b & c > d');

    expect($w->outputMemory())->toBe('<p>a &lt; b &amp; c &gt; d</p>');
});

it('passes raw content through writeRaw without escaping', function () {
    $w = new XmlWriter;
    $w->openMemory();
    $w->startElement('parent');
    $w->writeRaw('<child>raw &amp; passed-through</child>');
    $w->endElement();

    expect($w->outputMemory())->toBe(
        '<parent><child>raw &amp; passed-through</child></parent>'
    );
});

it('supports nested elements', function () {
    $w = new XmlWriter;
    $w->openMemory();
    $w->startElement('outer');
    $w->writeAttribute('id', '1');
    $w->startElement('inner');
    $w->text('value');
    $w->endElement();
    $w->endElement();

    expect($w->outputMemory())->toBe('<outer id="1"><inner>value</inner></outer>');
});

it('endDocument auto-closes any open elements', function () {
    $w = new XmlWriter;
    $w->openMemory();
    $w->startElement('a');
    $w->startElement('b');
    $w->endDocument();

    expect($w->outputMemory())->toBe('<a><b/></a>');
});

it('utf-8 multi-byte characters pass through unchanged', function () {
    $w = new XmlWriter;
    $w->openMemory();
    $w->writeElement('p', 'Hans ließ — schöne Grüße');

    expect($w->outputMemory())->toBe('<p>Hans ließ — schöne Grüße</p>');
});

it('writes to a file via openUri + flush', function () {
    $path = tempnam(sys_get_temp_dir(), 'xmlwriter-poly-');
    $w = new XmlWriter;
    $w->openUri($path);
    $w->writeElement('hello');
    $w->flush();

    expect(file_get_contents($path))->toBe('<hello/>');
    @unlink($path);
});

it('outputMemory returns and clears buffer when flush=true', function () {
    $w = new XmlWriter;
    $w->openMemory();
    $w->writeElement('a');
    expect($w->outputMemory(true))->toBe('<a/>');
    expect($w->outputMemory(true))->toBe('');
});

it('produces a valid PhpWord docx using the polyfill', function () {
    $original = class_exists('XMLWriter', false) ? get_class(new \XMLWriter) : null;

    $reflection = new ReflectionClass(PhpOffice\PhpWord\Shared\XMLWriter::class);
    expect($reflection->getParentClass()->getName())->toBe('XMLWriter');

    $phpWord = new PhpWord;
    $section = $phpWord->addSection();
    $section->addText('Polyfill round-trip');

    $tmp = tempnam(sys_get_temp_dir(), 'docx-poly-');
    rename($tmp, $tmp .= '.docx');
    $phpWord->save($tmp);

    expect(filesize($tmp))->toBeGreaterThan(0);
    $zip = new ZipArchive;
    expect($zip->open($tmp))->toBeTrue();
    expect($zip->locateName('word/document.xml'))->not->toBeFalse();
    $doc = $zip->getFromName('word/document.xml');
    expect($doc)->toContain('Polyfill round-trip');
    $zip->close();
    @unlink($tmp);
});
