<?php

namespace App\Services\Parsers;

use App\Contracts\DocumentParserInterface;
use App\Services\Parsers\Concerns\DetectsChapters;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use ZipArchive;

class EpubParserService implements DocumentParserInterface
{
    use DetectsChapters;

    private const NS_CONTAINER = 'urn:oasis:names:tc:opendocument:xmlns:container';

    private const NS_OPF = 'http://www.idpf.org/2007/opf';

    /**
     * Parse an .epub file and extract chapters by heading detection.
     *
     * @return array{chapters: list<array{number: int, title: string, word_count: int, content: string}>}
     */
    public function parse(UploadedFile $file): array
    {
        $zip = new ZipArchive;

        if ($zip->open($file->getRealPath()) !== true) {
            return $this->fallbackSingleChapter([]);
        }

        $opfPath = $this->resolveOpfPath($zip);

        if ($opfPath === null) {
            $zip->close();

            return $this->fallbackSingleChapter([]);
        }

        $opfDir = dirname($opfPath);
        if ($opfDir === '.') {
            $opfDir = '';
        } else {
            $opfDir .= '/';
        }

        $spineFiles = $this->resolveSpineFiles($zip, $opfPath, $opfDir);

        if ($spineFiles === []) {
            $zip->close();

            return $this->fallbackSingleChapter([]);
        }

        $paragraphs = [];

        foreach ($spineFiles as $xhtmlPath) {
            $xhtml = $zip->getFromName($xhtmlPath);

            if ($xhtml === false) {
                continue;
            }

            array_push($paragraphs, ...$this->extractParagraphs($xhtml));
        }

        $zip->close();

        $chapters = $this->splitIntoChapters($paragraphs);

        if (count($chapters) === 0) {
            return $this->fallbackSingleChapter($paragraphs);
        }

        return ['chapters' => $chapters];
    }

    /**
     * Read META-INF/container.xml to find the OPF file path.
     */
    private function resolveOpfPath(ZipArchive $zip): ?string
    {
        $containerXml = $zip->getFromName('META-INF/container.xml');

        if ($containerXml === false) {
            return null;
        }

        $dom = new DOMDocument;
        $dom->loadXML($containerXml, LIBXML_NOERROR | LIBXML_NOWARNING);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('container', self::NS_CONTAINER);

        $rootfile = $xpath->query('//container:rootfile/@full-path');

        if ($rootfile === false || $rootfile->length === 0) {
            return null;
        }

        return $rootfile->item(0)->nodeValue;
    }

    /**
     * Parse the OPF file and return an ordered list of XHTML paths from the spine.
     *
     * @return list<string>
     */
    private function resolveSpineFiles(ZipArchive $zip, string $opfPath, string $opfDir): array
    {
        $opfXml = $zip->getFromName($opfPath);

        if ($opfXml === false) {
            return [];
        }

        $dom = new DOMDocument;
        $dom->loadXML($opfXml, LIBXML_NOERROR | LIBXML_NOWARNING);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('opf', self::NS_OPF);

        $manifest = [];
        $items = $xpath->query('//opf:manifest/opf:item');

        if ($items !== false) {
            foreach ($items as $item) {
                $id = $item->getAttribute('id');
                $href = $item->getAttribute('href');
                $mediaType = $item->getAttribute('media-type');

                if ($mediaType === 'application/xhtml+xml') {
                    $manifest[$id] = $href;
                }
            }
        }

        $spineRefs = $xpath->query('//opf:spine/opf:itemref');
        $files = [];

        if ($spineRefs !== false) {
            foreach ($spineRefs as $ref) {
                $idref = $ref->getAttribute('idref');

                if (isset($manifest[$idref])) {
                    $files[] = $opfDir.$manifest[$idref];
                }
            }
        }

        return $files;
    }

    /**
     * Parse XHTML content and extract paragraphs with style info and HTML.
     *
     * @return list<array{style: string|null, text: string, html: string}>
     */
    private function extractParagraphs(string $xhtml): array
    {
        $dom = new DOMDocument;

        if (@$dom->loadXML($xhtml, LIBXML_NOERROR | LIBXML_NOWARNING) === false) {
            @$dom->loadHTML($xhtml, LIBXML_NOERROR | LIBXML_NOWARNING);
        }

        $xpath = new DOMXPath($dom);
        $paragraphs = [];

        // Use local-name() to handle XHTML namespace
        $nodes = $xpath->query(
            '//*[local-name()="h1" or local-name()="h2" or local-name()="h3"'
            .' or local-name()="h4" or local-name()="h5" or local-name()="h6"'
            .' or local-name()="p" or local-name()="blockquote" or local-name()="hr"]'
        );

        if ($nodes === false) {
            return [];
        }

        foreach ($nodes as $node) {
            $tagName = strtolower($node->localName);

            // Skip <p> elements that are direct children of <blockquote>
            // since we handle them as part of the blockquote processing
            if ($tagName === 'p' && $node->parentNode !== null && strtolower($node->parentNode->localName) === 'blockquote') {
                continue;
            }

            if ($tagName === 'hr') {
                $paragraphs[] = [
                    'style' => null,
                    'text' => '***',
                    'html' => '<hr>',
                ];

                continue;
            }

            if ($tagName === 'blockquote') {
                $inline = $this->extractInline($node);

                if (trim($inline['text']) !== '') {
                    $paragraphs[] = [
                        'style' => null,
                        'text' => $inline['text'],
                        'html' => '<blockquote><p>'.$inline['html'].'</p></blockquote>',
                    ];
                }

                continue;
            }

            $style = null;

            if (preg_match('/^h([1-6])$/', $tagName, $matches)) {
                $level = (int) $matches[1];
                $style = ($level <= 2) ? 'Heading'.$level : null;
            }

            $inline = $this->extractInline($node);
            $inlineHtml = $this->mergeAdjacentTags($inline['html']);
            $isScene = $this->isSceneBreak(null, $inline['text']);

            if (trim($inline['text']) !== '' || $isScene) {
                $paragraphs[] = [
                    'style' => $style,
                    'text' => $inline['text'],
                    'html' => $this->wrapParagraph($inlineHtml, $style, $isScene),
                ];
            }
        }

        return $paragraphs;
    }

    /**
     * Extract both plain text and formatted HTML from a node in a single walk.
     *
     * @return array{text: string, html: string}
     */
    private function extractInline(DOMNode $node): array
    {
        $text = '';
        $html = '';

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text .= $child->textContent;
                $html .= htmlspecialchars($child->textContent, ENT_QUOTES, 'UTF-8');
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $childTag = strtolower($child->localName);

                if ($childTag === 'br') {
                    $text .= ' ';
                    $html .= '<br>';
                } elseif (in_array($childTag, ['strong', 'b'])) {
                    $inner = $this->extractInline($child);
                    $text .= $inner['text'];
                    $html .= '<strong>'.$inner['html'].'</strong>';
                } elseif (in_array($childTag, ['em', 'i'])) {
                    $inner = $this->extractInline($child);
                    $text .= $inner['text'];
                    $html .= '<em>'.$inner['html'].'</em>';
                } elseif ($childTag === 'u') {
                    $inner = $this->extractInline($child);
                    $text .= $inner['text'];
                    $html .= '<u>'.$inner['html'].'</u>';
                } else {
                    $inner = $this->extractInline($child);
                    $text .= $inner['text'];
                    $html .= $inner['html'];
                }
            }
        }

        return ['text' => $text, 'html' => $html];
    }
}
