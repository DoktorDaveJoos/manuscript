<?php

namespace App\Services\Export\Exporters;

use App\Contracts\Exporter;
use App\Models\Book;
use App\Services\Export\ContentPreparer;
use App\Services\Export\ExportOptions;
use App\Services\Export\ExportService;
use App\Services\Export\FontService;
use Illuminate\Support\Collection;
use ZipArchive;

class EpubExporter implements Exporter
{
    public function __construct(
        private ContentPreparer $contentPreparer,
        private FontService $fontService,
    ) {}

    public function export(Book $book, Collection $chapters, ExportOptions $options): string
    {
        $filename = ExportService::tempPath('epub');
        $uuid = $this->generateUuid($book);

        $zip = new ZipArchive;
        $zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // mimetype must be first entry, uncompressed
        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->setCompressionName('mimetype', ZipArchive::CM_STORE);

        $this->addContainerXml($zip);
        $this->addStylesheet($zip);
        $this->addFonts($zip);

        $chapterFiles = $this->addChapters($zip, $chapters, $options);

        if ($options->includeTableOfContents) {
            $this->addTocXhtml($zip, $chapters);
        }
        $this->addTocNcx($zip, $book, $chapters, $uuid);
        $this->addContentOpf($zip, $book, $chapters, $chapterFiles, $options, $uuid);

        $zip->close();

        return $filename;
    }

    private function addContainerXml(ZipArchive $zip): void
    {
        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
          <rootfiles>
            <rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/>
          </rootfiles>
        </container>
        XML;

        $zip->addFromString('META-INF/container.xml', $xml);
    }

    private function addStylesheet(ZipArchive $zip): void
    {
        $fontFace = $this->fontService->epubFontFaceCss();

        $css = <<<CSS
        {$fontFace}

        body {
            font-family: "Literata", Georgia, serif;
            font-size: 1em;
            line-height: 1.6;
            margin: 1em;
            text-align: justify;
        }
        h1 {
            font-size: 1.8em;
            font-weight: 700;
            margin: 2em 0 1em;
            text-align: center;
            page-break-before: always;
        }
        h1:first-child {
            page-break-before: avoid;
        }
        h2 {
            font-size: 1.4em;
            font-weight: 700;
            margin: 2em 0 0.5em;
            text-align: center;
        }
        p {
            margin: 0;
            text-indent: 1.5em;
        }
        p:first-child,
        hr.scene-break + p,
        h1 + p,
        h2 + p {
            text-indent: 0;
        }
        hr.scene-break {
            border: none;
            text-align: center;
            margin: 1.5em 0;
        }
        hr.scene-break::after {
            content: "* * *";
            font-style: italic;
        }
        .act-break {
            font-size: 1.6em;
            font-weight: 700;
            text-align: center;
            margin: 3em 0 1em;
        }
        nav#toc ol {
            list-style: none;
            padding: 0;
        }
        nav#toc ol li {
            margin: 0.5em 0;
        }
        nav#toc ol li a {
            text-decoration: none;
            color: inherit;
        }
        CSS;

        $zip->addFromString('OEBPS/Styles/stylesheet.css', $css);
    }

    private function addFonts(ZipArchive $zip): void
    {
        if (! $this->fontService->fontsAvailable()) {
            return;
        }

        $zip->addFile($this->fontService->regularFontPath(), 'OEBPS/Fonts/Literata.ttf');
        $zip->addFile($this->fontService->italicFontPath(), 'OEBPS/Fonts/Literata-Italic.ttf');
    }

    /**
     * @return array<int, string>
     */
    private function addChapters(ZipArchive $zip, Collection $chapters, ExportOptions $options): array
    {
        $chapterFiles = [];
        $currentActId = null;

        foreach ($chapters as $index => $chapter) {
            $num = $this->chapterNum($index);
            $fileName = "chapter-{$num}.xhtml";
            $chapterFiles[] = $fileName;

            $body = '';

            if ($options->includeActBreaks && $chapter->act_id && $chapter->act_id !== $currentActId) {
                $currentActId = $chapter->act_id;
                $actTitle = htmlspecialchars($chapter->act?->title ?? "Act {$chapter->act?->number}", ENT_XML1, 'UTF-8');
                $body .= "<p class=\"act-break\">{$actTitle}</p>\n";
            }

            if ($options->includeChapterTitles) {
                $title = htmlspecialchars($chapter->title, ENT_XML1, 'UTF-8');
                $body .= "<h1>{$title}</h1>\n";
            }

            $scenes = $chapter->scenes ?? collect();
            foreach ($scenes as $sceneIndex => $scene) {
                if ($sceneIndex > 0) {
                    $body .= "<hr class=\"scene-break\" />\n";
                }

                $content = $scene->content ?? '';
                $body .= $this->contentPreparer->toXhtml($content);
            }

            $chapterTitle = htmlspecialchars($chapter->title, ENT_XML1, 'UTF-8');
            $xhtml = $this->wrapXhtml($chapterTitle, $body);
            $zip->addFromString("OEBPS/Text/{$fileName}", $xhtml);
        }

        return $chapterFiles;
    }

    private function addTocXhtml(ZipArchive $zip, Collection $chapters): void
    {
        $items = '';
        foreach ($chapters as $index => $chapter) {
            $num = $this->chapterNum($index);
            $title = htmlspecialchars($chapter->title, ENT_XML1, 'UTF-8');
            $items .= "        <li><a href=\"Text/chapter-{$num}.xhtml\">{$title}</a></li>\n";
        }

        $body = <<<HTML
        <nav epub:type="toc" id="toc">
          <h1>Table of Contents</h1>
          <ol>
        {$items}  </ol>
        </nav>
        HTML;

        $xhtml = $this->wrapXhtml('Table of Contents', $body, true);
        $zip->addFromString('OEBPS/toc.xhtml', $xhtml);
    }

    private function addTocNcx(ZipArchive $zip, Book $book, Collection $chapters, string $uuid): void
    {
        $bookTitle = htmlspecialchars($book->title, ENT_XML1, 'UTF-8');
        $navPoints = '';

        foreach ($chapters as $index => $chapter) {
            $num = $this->chapterNum($index);
            $title = htmlspecialchars($chapter->title, ENT_XML1, 'UTF-8');
            $playOrder = $index + 1;
            $navPoints .= <<<XML
                <navPoint id="chapter-{$num}" playOrder="{$playOrder}">
                  <navLabel><text>{$title}</text></navLabel>
                  <content src="Text/chapter-{$num}.xhtml"/>
                </navPoint>

            XML;
        }

        $ncx = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
          <head>
            <meta name="dtb:uid" content="urn:uuid:{$uuid}"/>
          </head>
          <docTitle><text>{$bookTitle}</text></docTitle>
          <navMap>
        {$navPoints}  </navMap>
        </ncx>
        XML;

        $zip->addFromString('OEBPS/toc.ncx', $ncx);
    }

    /**
     * @param  array<int, string>  $chapterFiles
     */
    private function addContentOpf(ZipArchive $zip, Book $book, Collection $chapters, array $chapterFiles, ExportOptions $options, string $uuid): void
    {
        $title = htmlspecialchars($book->title, ENT_XML1, 'UTF-8');
        $author = htmlspecialchars($book->author ?? 'Unknown', ENT_XML1, 'UTF-8');
        $language = htmlspecialchars($book->language ?? 'en', ENT_XML1, 'UTF-8');
        $modified = now()->format('Y-m-d\TH:i:s\Z');

        // Build manifest
        $manifestItems = <<<'XML'
            <item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/>
            <item id="stylesheet" href="Styles/stylesheet.css" media-type="text/css"/>
        XML;

        if ($options->includeTableOfContents) {
            $manifestItems .= "\n    <item id=\"toc\" href=\"toc.xhtml\" media-type=\"application/xhtml+xml\" properties=\"nav\"/>";
        }

        if ($this->fontService->fontsAvailable()) {
            $manifestItems .= "\n    <item id=\"font-regular\" href=\"Fonts/Literata.ttf\" media-type=\"font/ttf\"/>";
            $manifestItems .= "\n    <item id=\"font-italic\" href=\"Fonts/Literata-Italic.ttf\" media-type=\"font/ttf\"/>";
        }

        foreach ($chapterFiles as $index => $file) {
            $num = $this->chapterNum($index);
            $manifestItems .= "\n    <item id=\"chapter-{$num}\" href=\"Text/{$file}\" media-type=\"application/xhtml+xml\"/>";
        }

        // Build spine
        $spineItems = '';
        if ($options->includeTableOfContents) {
            $spineItems .= "    <itemref idref=\"toc\"/>\n";
        }
        foreach ($chapterFiles as $index => $file) {
            $num = $this->chapterNum($index);
            $spineItems .= "    <itemref idref=\"chapter-{$num}\"/>\n";
        }

        $opf = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <package xmlns="http://www.idpf.org/2007/opf" unique-identifier="BookId" version="3.0">
          <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
            <dc:identifier id="BookId">urn:uuid:{$uuid}</dc:identifier>
            <dc:title>{$title}</dc:title>
            <dc:creator>{$author}</dc:creator>
            <dc:language>{$language}</dc:language>
            <meta property="dcterms:modified">{$modified}</meta>
          </metadata>
          <manifest>
        {$manifestItems}
          </manifest>
          <spine toc="ncx">
        {$spineItems}  </spine>
        </package>
        XML;

        $zip->addFromString('OEBPS/content.opf', $opf);
    }

    private function wrapXhtml(string $title, string $body, bool $isNav = false): string
    {
        $epubNs = $isNav ? ' xmlns:epub="http://www.idpf.org/2007/ops"' : '';

        return <<<XHTML
        <?xml version="1.0" encoding="UTF-8"?>
        <!DOCTYPE html>
        <html xmlns="http://www.w3.org/1999/xhtml"{$epubNs} xml:lang="en">
        <head>
          <meta charset="UTF-8"/>
          <title>{$title}</title>
          <link rel="stylesheet" type="text/css" href="../Styles/stylesheet.css"/>
        </head>
        <body>
        {$body}
        </body>
        </html>
        XHTML;
    }

    private function chapterNum(int $index): string
    {
        return str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
    }

    private function generateUuid(Book $book): string
    {
        // Deterministic UUID based on book ID for consistency
        return sprintf(
            '%08x-%04x-4%03x-%04x-%012x',
            $book->id,
            ($book->id >> 16) & 0xFFFF,
            $book->id & 0xFFF,
            (($book->id >> 8) & 0x3FFF) | 0x8000,
            $book->id * 2654435761,
        );
    }
}
