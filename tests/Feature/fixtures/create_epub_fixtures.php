<?php

/**
 * Generate .epub test fixtures.
 * Run once: php tests/Feature/fixtures/create_epub_fixtures.php
 */
function createEpub(string $path, string $containerXml, string $opfXml, array $xhtmlFiles): void
{
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    // mimetype must be first entry, stored uncompressed (EPUB spec requirement)
    $zip->addFromString('mimetype', 'application/epub+zip');
    $zip->setCompressionName('mimetype', ZipArchive::CM_STORE);

    $zip->addFromString('META-INF/container.xml', $containerXml);
    $zip->addFromString('OEBPS/content.opf', $opfXml);

    foreach ($xhtmlFiles as $name => $content) {
        $zip->addFromString("OEBPS/{$name}", $content);
    }

    $zip->close();

    echo "Created: {$path}\n";
}

$containerXml = '<?xml version="1.0" encoding="UTF-8"?>
<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <rootfiles>
    <rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>';

// Fixture 1: EPUB with 3 chapters, each with an <h1> heading
$chaptersOpf = '<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf" version="3.0">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:title>Test Book</dc:title>
  </metadata>
  <manifest>
    <item id="ch1" href="chapter1.xhtml" media-type="application/xhtml+xml"/>
    <item id="ch2" href="chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="ch3" href="chapter3.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="ch1"/>
    <itemref idref="ch2"/>
    <itemref idref="ch3"/>
  </spine>
</package>';

$xhtmlTemplate = fn (string $title, string $body) => '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head><title>'.$title.'</title></head>
<body>
<h1>'.$title.'</h1>
<p>'.$body.'</p>
</body>
</html>';

createEpub(__DIR__.'/chapters.epub', $containerXml, $chaptersOpf, [
    'chapter1.xhtml' => $xhtmlTemplate('The Morning After', 'The sun rose slowly over the valley.'),
    'chapter2.xhtml' => $xhtmlTemplate('Echoes', 'The hallway stretched endlessly before her.'),
    'chapter3.xhtml' => $xhtmlTemplate('The Garden Wall', 'Ivy crept along the old stones.'),
]);

// Fixture 2: EPUB without headings (should fallback to single chapter)
$noHeadingsOpf = '<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf" version="3.0">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:title>No Headings</dc:title>
  </metadata>
  <manifest>
    <item id="content" href="content.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="content"/>
  </spine>
</package>';

$noHeadingsXhtml = '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head><title>No Headings</title></head>
<body>
<p>The morning was cold and still.</p>
<p>She walked down the path.</p>
</body>
</html>';

createEpub(__DIR__.'/no-headings.epub', $containerXml, $noHeadingsOpf, [
    'content.xhtml' => $noHeadingsXhtml,
]);

// Fixture 3: EPUB with formatting (bold, italic, underline, blockquote, scene break, special chars, br)
$formattedOpf = '<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf" version="3.0">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:title>Formatted</dc:title>
  </metadata>
  <manifest>
    <item id="ch1" href="chapter1.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="ch1"/>
  </spine>
</package>';

$formattedXhtml = '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head><title>Formatted Chapter</title></head>
<body>
<h1>Formatted Chapter</h1>
<p><strong>bold text</strong></p>
<p><em>italic text</em></p>
<p><u>underlined</u></p>
<blockquote><p>This is a blockquote paragraph.</p></blockquote>
<hr/>
<p>After the break.</p>
<p>Tom &amp; Jerry</p>
<p>&lt;script&gt;</p>
<p>Line one<br/>line two after break.</p>
</body>
</html>';

createEpub(__DIR__.'/formatted.epub', $containerXml, $formattedOpf, [
    'chapter1.xhtml' => $formattedXhtml,
]);
