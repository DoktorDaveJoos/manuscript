<?php

/**
 * Generate .odt test fixtures.
 * Run once: php tests/Feature/fixtures/create_odt_fixtures.php
 */
function createOdt(string $path, string $contentXml): void
{
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    // mimetype must be first entry, stored uncompressed (ODF spec requirement)
    $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.text');
    $zip->setCompressionName('mimetype', ZipArchive::CM_STORE);

    $zip->addFromString('META-INF/manifest.xml', '<?xml version="1.0" encoding="UTF-8"?>
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.2">
  <manifest:file-entry manifest:media-type="application/vnd.oasis.opendocument.text" manifest:full-path="/"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/>
</manifest:manifest>');

    $zip->addFromString('content.xml', $contentXml);
    $zip->close();

    echo "Created: {$path}\n";
}

// Fixture 1: ODT with headings and content
createOdt(__DIR__.'/chapters.odt', '<?xml version="1.0" encoding="UTF-8"?>
<office:document-content
    xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
    xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
    xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
    xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0"
    office:version="1.2">
  <office:body>
    <office:text>
      <text:h text:outline-level="1">Chapter 1: The Morning After</text:h>
      <text:p>The sun rose slowly over the valley.</text:p>
      <text:h text:outline-level="1">Chapter 2: Echoes</text:h>
      <text:p>The hallway stretched endlessly before her.</text:p>
      <text:h text:outline-level="2">The Garden Wall</text:h>
      <text:p>Ivy crept along the old stones.</text:p>
    </office:text>
  </office:body>
</office:document-content>');

// Fixture 2: ODT without headings (should fallback to single chapter)
createOdt(__DIR__.'/no-headings.odt', '<?xml version="1.0" encoding="UTF-8"?>
<office:document-content
    xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
    xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
    xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
    xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0"
    office:version="1.2">
  <office:body>
    <office:text>
      <text:p>The morning was cold and still.</text:p>
      <text:p>She walked down the path.</text:p>
    </office:text>
  </office:body>
</office:document-content>');

// Fixture 3: ODT with formatting (bold, italic, underline, scene break)
createOdt(__DIR__.'/formatted.odt', '<?xml version="1.0" encoding="UTF-8"?>
<office:document-content
    xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
    xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
    xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
    xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0"
    office:version="1.2">
  <office:automatic-styles>
    <style:style style:name="Bold" style:family="text">
      <style:text-properties fo:font-weight="bold"/>
    </style:style>
    <style:style style:name="Italic" style:family="text">
      <style:text-properties fo:font-style="italic"/>
    </style:style>
    <style:style style:name="Underline" style:family="text">
      <style:text-properties style:text-underline-style="solid"/>
    </style:style>
  </office:automatic-styles>
  <office:body>
    <office:text>
      <text:h text:outline-level="1">Formatted Chapter</text:h>
      <text:p><text:span text:style-name="Bold">bold text</text:span></text:p>
      <text:p><text:span text:style-name="Italic">italic text</text:span></text:p>
      <text:p><text:span text:style-name="Underline">underlined</text:span></text:p>
      <text:p>***</text:p>
      <text:p>After the break.</text:p>
    </office:text>
  </office:body>
</office:document-content>');
