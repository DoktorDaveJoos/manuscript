<?php

namespace App\Services\Parsers;

use App\Contracts\DocumentParserInterface;
use App\Services\Parsers\Concerns\DetectsChapters;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use ZipArchive;

class OdtParserService implements DocumentParserInterface
{
    use DetectsChapters;

    private const NS_OFFICE = 'urn:oasis:names:tc:opendocument:xmlns:office:1.0';

    private const NS_TEXT = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';

    private const NS_STYLE = 'urn:oasis:names:tc:opendocument:xmlns:style:1.0';

    private const NS_FO = 'urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0';

    /**
     * Resolved automatic styles: styleName => ['bold' => bool, 'italic' => bool, 'underline' => bool].
     *
     * @var array<string, array{bold: bool, italic: bool, underline: bool}>
     */
    private array $autoStyles = [];

    /**
     * Parse an .odt file and extract chapters by heading detection.
     *
     * @return array{chapters: list<array{number: int, title: string, word_count: int, content: string}>}
     */
    public function parse(UploadedFile $file): array
    {
        $xml = $this->extractContentXml($file);

        if ($xml === null) {
            return $this->fallbackSingleChapter([]);
        }

        $paragraphs = $this->extractParagraphs($xml);
        $chapters = $this->splitIntoChapters($paragraphs);

        if (count($chapters) === 0) {
            return $this->fallbackSingleChapter($paragraphs);
        }

        return ['chapters' => $chapters];
    }

    /**
     * Extract content.xml from the ODT ZIP archive.
     */
    private function extractContentXml(UploadedFile $file): ?string
    {
        $zip = new ZipArchive;

        if ($zip->open($file->getRealPath()) !== true) {
            return null;
        }

        $xml = $zip->getFromName('content.xml');
        $zip->close();

        return $xml ?: null;
    }

    /**
     * Parse the XML and extract paragraphs with their style info and HTML.
     *
     * @return list<array{style: string|null, text: string, html: string}>
     */
    private function extractParagraphs(string $xml): array
    {
        $dom = new DOMDocument;
        $dom->loadXML($xml, LIBXML_NOERROR | LIBXML_NOWARNING);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('office', self::NS_OFFICE);
        $xpath->registerNamespace('text', self::NS_TEXT);
        $xpath->registerNamespace('style', self::NS_STYLE);
        $xpath->registerNamespace('fo', self::NS_FO);

        $this->resolveAutoStyles($xpath);

        $paragraphs = [];

        // Process both text:h (headings) and text:p (paragraphs) in document order
        $nodes = $xpath->query('//office:body//text:h | //office:body//text:p');

        if ($nodes === false) {
            return [];
        }

        foreach ($nodes as $node) {
            $isHeading = $node->localName === 'h';
            $style = null;

            if ($isHeading) {
                $outlineLevel = $node->getAttributeNS(self::NS_TEXT, 'outline-level');
                $level = (int) $outlineLevel;
                $style = ($level <= 2) ? 'Heading'.$level : null;
            } else {
                $styleName = $node->getAttributeNS(self::NS_TEXT, 'style-name');
                $style = ($styleName !== '') ? $styleName : null;
            }

            $rawText = '';
            $htmlParts = [];

            foreach ($node->childNodes as $child) {
                if ($child->nodeType === XML_TEXT_NODE) {
                    $text = $child->textContent;
                    $rawText .= $text;
                    $htmlParts[] = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
                } elseif ($child->localName === 'span') {
                    $result = $this->buildSpanHtml($child);
                    $rawText .= $result['text'];
                    $htmlParts[] = $result['html'];
                }
            }

            $inlineHtml = $this->mergeAdjacentTags(implode('', $htmlParts));
            $isScene = $this->isSceneBreak(null, $rawText, null);

            if (trim($rawText) !== '' || $isScene) {
                $paragraphs[] = [
                    'style' => $style,
                    'text' => $rawText,
                    'html' => $this->wrapParagraph($inlineHtml, $style, $isScene),
                ];
            }
        }

        return $paragraphs;
    }

    /**
     * Resolve automatic styles from office:automatic-styles.
     */
    private function resolveAutoStyles(DOMXPath $xpath): void
    {
        $this->autoStyles = [];

        $styles = $xpath->query('//office:automatic-styles/style:style[@style:family="text"]');

        if ($styles === false) {
            return;
        }

        foreach ($styles as $style) {
            $name = $style->getAttributeNS(self::NS_STYLE, 'name');

            if ($name === '') {
                continue;
            }

            $props = $xpath->query('style:text-properties', $style);

            if ($props === false || $props->length === 0) {
                continue;
            }

            $propNode = $props->item(0);

            $fontWeight = $propNode->getAttributeNS(self::NS_FO, 'font-weight');
            $fontStyle = $propNode->getAttributeNS(self::NS_FO, 'font-style');
            $underlineStyle = $propNode->getAttributeNS(self::NS_STYLE, 'text-underline-style');

            $this->autoStyles[$name] = [
                'bold' => $fontWeight === 'bold',
                'italic' => $fontStyle === 'italic',
                'underline' => $underlineStyle !== '' && $underlineStyle !== 'none',
            ];
        }
    }

    /**
     * Build HTML for a text:span element using its automatic style.
     *
     * @return array{text: string, html: string}
     */
    private function buildSpanHtml(DOMNode $span): array
    {
        $text = $span->textContent;
        $html = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        $styleName = $span->getAttributeNS(self::NS_TEXT, 'style-name');

        if ($styleName !== '' && isset($this->autoStyles[$styleName])) {
            $formatting = $this->autoStyles[$styleName];

            if ($formatting['underline']) {
                $html = '<u>'.$html.'</u>';
            }
            if ($formatting['italic']) {
                $html = '<em>'.$html.'</em>';
            }
            if ($formatting['bold']) {
                $html = '<strong>'.$html.'</strong>';
            }
        }

        return ['text' => $text, 'html' => $html];
    }

    /**
     * Detect if a paragraph is a blockquote.
     */
    private function isBlockquote(?string $style): bool
    {
        if ($style === null) {
            return false;
        }

        return (bool) preg_match('/^(Quote|Quotations|BlockQuote|Block\s*Text|IntenseQuote)$/i', $style);
    }

    /**
     * Wrap inline HTML in the appropriate block element.
     */
    private function wrapParagraph(string $inlineHtml, ?string $style, bool $isScene): string
    {
        if ($isScene) {
            return '<hr>';
        }

        if ($this->isBlockquote($style)) {
            return '<blockquote><p>'.$inlineHtml.'</p></blockquote>';
        }

        return '<p>'.$inlineHtml.'</p>';
    }

    /**
     * Merge adjacent identical inline tags, preserving any whitespace between them.
     */
    private function mergeAdjacentTags(string $html): string
    {
        $previous = '';
        while ($previous !== $html) {
            $previous = $html;
            $html = preg_replace('#</em>(\s*)<em>#', '$1', $html);
            $html = preg_replace('#</strong>(\s*)<strong>#', '$1', $html);
            $html = preg_replace('#</u>(\s*)<u>#', '$1', $html);
        }

        return $html;
    }
}
