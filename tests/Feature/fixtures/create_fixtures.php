<?php

/**
 * Generate .docx test fixtures.
 * Run once: php tests/Feature/fixtures/create_fixtures.php
 */
function createDocx(string $path, string $documentXml): void
{
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>');

    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>');

    $zip->addFromString('word/document.xml', $documentXml);
    $zip->close();

    echo "Created: {$path}\n";
}

// Fixture 1: Multiple chapters with Heading1 styles
createDocx(__DIR__.'/chapters.docx', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Chapter 1: The Morning After</w:t></w:r></w:p>
    <w:p><w:r><w:t>The sun rose slowly over the quiet town. It was a morning like any other yet everything had changed.</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Chapter 2: Echoes</w:t></w:r></w:p>
    <w:p><w:r><w:t>The hallway stretched endlessly before her. Each step echoed in the silence of the abandoned building.</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Chapter 3: The Garden Wall</w:t></w:r></w:p>
    <w:p><w:r><w:t>Behind the old stone wall lay a forgotten garden full of wild roses and untamed ivy.</w:t></w:r></w:p>
  </w:body>
</w:document>');

// Fixture 2: No headings (should fallback to single chapter)
createDocx(__DIR__.'/no-headings.docx', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>This is a document without any chapter headings at all.</w:t></w:r></w:p>
    <w:p><w:r><w:t>It should be imported as a single chapter called Full Document.</w:t></w:r></w:p>
  </w:body>
</w:document>');

// Fixture 3: Consecutive headings (part title then chapter, trailing heading with no body)
createDocx(__DIR__.'/consecutive-headings.docx', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Part One</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Chapter 1: Into the Woods</w:t></w:r></w:p>
    <w:p><w:r><w:t>The forest was dark and deep. She stepped carefully over the twisted roots.</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Chapter 2: The Clearing</w:t></w:r></w:p>
    <w:p><w:r><w:t>Sunlight broke through the canopy and illuminated a small clearing ahead.</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Appendix</w:t></w:r></w:p>
  </w:body>
</w:document>');

// Fixture 4: Rich formatting (bold, italic, underline, scene break, blockquote, line break, special chars)
createDocx(__DIR__.'/formatted.docx', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Chapter 1: Formatting Test</w:t></w:r></w:p>
    <w:p><w:r><w:t>Normal text then </w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>bold text</w:t></w:r><w:r><w:t> and </w:t></w:r><w:r><w:rPr><w:i/></w:rPr><w:t>italic text</w:t></w:r><w:r><w:t> and </w:t></w:r><w:r><w:rPr><w:u w:val="single"/></w:rPr><w:t>underlined</w:t></w:r><w:r><w:t> end.</w:t></w:r></w:p>
    <w:p><w:r><w:rPr><w:b/><w:i/></w:rPr><w:t>Bold and italic together.</w:t></w:r></w:p>
    <w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:t>* * *</w:t></w:r></w:p>
    <w:p><w:r><w:t>After the scene break.</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="Quote"/></w:pPr><w:r><w:t>This is a blockquote paragraph.</w:t></w:r></w:p>
    <w:p><w:r><w:t>Line one</w:t></w:r><w:r><w:br/></w:r><w:r><w:t>line two after break.</w:t></w:r></w:p>
    <w:p><w:r><w:t>Tom &amp; Jerry &lt;script&gt; test.</w:t></w:r></w:p>
  </w:body>
</w:document>');
