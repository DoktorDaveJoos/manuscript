<?php

namespace App\Services\Parsers;

use App\Contracts\DocumentParserInterface;
use App\Services\Parsers\Concerns\DetectsChapters;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use ZipArchive;

class DocxParserService implements DocumentParserInterface
{
    use DetectsChapters;

    /**
     * Parse a .docx file and extract chapters by heading detection.
     *
     * @return array{chapters: list<array{number: int, title: string, word_count: int, content: string}>}
     */
    public function parse(UploadedFile $file): array
    {
        $xml = $this->extractDocumentXml($file);

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
     * Extract the document.xml content from a .docx file.
     */
    private function extractDocumentXml(UploadedFile $file): ?string
    {
        $zip = new ZipArchive;

        if ($zip->open($file->getRealPath()) !== true) {
            return null;
        }

        $xml = $zip->getFromName('word/document.xml');
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
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $paragraphs = [];
        $nodes = $xpath->query('//w:p');

        if ($nodes === false) {
            return [];
        }

        foreach ($nodes as $node) {
            $styleNode = $xpath->query('.//w:pPr/w:pStyle/@w:val', $node);
            $style = ($styleNode && $styleNode->length > 0) ? $styleNode->item(0)->nodeValue : null;

            $jcNode = $xpath->query('.//w:pPr/w:jc/@w:val', $node);
            $alignment = ($jcNode && $jcNode->length > 0) ? $jcNode->item(0)->nodeValue : null;

            $rawText = '';
            $htmlParts = [];

            $runs = $xpath->query('.//w:r', $node);
            if ($runs) {
                foreach ($runs as $run) {
                    $runResult = $this->buildRunHtml($run, $xpath);
                    $rawText .= $runResult['text'];
                    $htmlParts[] = $runResult['html'];
                }
            }

            $inlineHtml = $this->mergeAdjacentTags(implode('', $htmlParts));
            $isScene = $this->isSceneBreak($style, $rawText, $alignment);

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
     * Build HTML for a single w:r (run) element.
     *
     * @return array{text: string, html: string}
     */
    private function buildRunHtml(DOMNode $run, DOMXPath $xpath): array
    {
        $text = '';
        $html = '';

        foreach ($run->childNodes as $child) {
            $localName = $child->localName;

            if ($localName === 't') {
                $escaped = htmlspecialchars($child->textContent, ENT_QUOTES, 'UTF-8');
                $text .= $child->textContent;
                $html .= $escaped;
            } elseif ($localName === 'br') {
                $text .= "\n";
                $html .= '<br>';
            }
        }

        if ($html === '') {
            return ['text' => $text, 'html' => ''];
        }

        $rPr = $xpath->query('./w:rPr', $run);
        if ($rPr && $rPr->length > 0) {
            $props = $rPr->item(0);
            $bold = $this->hasRunProperty($xpath, $props, 'w:b');
            $italic = $this->hasRunProperty($xpath, $props, 'w:i');
            $underline = $this->hasRunProperty($xpath, $props, 'w:u');

            if ($underline) {
                $html = '<u>'.$html.'</u>';
            }
            if ($italic) {
                $html = '<em>'.$html.'</em>';
            }
            if ($bold) {
                $html = '<strong>'.$html.'</strong>';
            }
        }

        return ['text' => $text, 'html' => $html];
    }

    /**
     * Check if a run property is set (present without val="false" or val="0").
     */
    private function hasRunProperty(DOMXPath $xpath, DOMNode $rPr, string $property): bool
    {
        $nodes = $xpath->query($property, $rPr);
        if (! $nodes || $nodes->length === 0) {
            return false;
        }

        $val = $xpath->query($property.'/@w:val', $rPr);
        if ($val && $val->length > 0) {
            $v = $val->item(0)->nodeValue;

            return $v !== 'false' && $v !== '0';
        }

        return true;
    }

    /**
     * Detect if a paragraph is a blockquote.
     */
    private function isBlockquote(?string $style): bool
    {
        if ($style === null) {
            return false;
        }

        return (bool) preg_match('/^(Quote|BlockQuote|Block\s*Text|IntenseQuote)$/i', $style);
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
     * Merge adjacent identical formatting tags, preserving any whitespace between them.
     *
     * Word often splits a single styled phrase into multiple runs, producing
     * fragments like `</em> <em>` that should be collapsed.
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
