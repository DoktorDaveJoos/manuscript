<?php

namespace App\Services;

use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use ZipArchive;

class DocxParserService
{
    /**
     * Parse a .docx file and extract chapters by heading detection.
     *
     * @return array{chapters: list<array{number: int, title: string, word_count: int, content: string}>}
     */
    public function parse(UploadedFile $file): array
    {
        $xml = $this->extractDocumentXml($file);

        if ($xml === null) {
            return $this->fallbackResult($file);
        }

        $paragraphs = $this->extractParagraphs($xml);
        $chapters = $this->splitIntoChapters($paragraphs);

        if (count($chapters) === 0) {
            return $this->fallbackResult($file);
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

            $inlineHtml = implode('', $htmlParts);
            $isScene = $this->isSceneBreak($style, $rawText, $alignment);

            if (trim($rawText) !== '' || $isScene) {
                $paragraphs[] = [
                    'style' => $style,
                    'text' => $rawText,
                    'html' => $this->wrapParagraph($inlineHtml, $style, $rawText, $alignment),
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
     * Detect if a paragraph is a scene break.
     */
    private function isSceneBreak(?string $style, string $text, ?string $alignment): bool
    {
        if ($style !== null && preg_match('/^(Separator|SceneBreak|Divider)$/i', $style)) {
            return true;
        }

        $trimmed = trim($text);
        if ($trimmed !== '' && preg_match('/^[\*\#\~\-\x{2014}\x{2013}\s]{1,20}$/u', $trimmed) && preg_match('/[\*\#\~\-\x{2014}\x{2013}]/u', $trimmed)) {
            return true;
        }

        if ($alignment === 'center' && mb_strlen($trimmed) <= 10 && $trimmed !== '' && preg_match('/^[\p{P}\p{S}\s]+$/u', $trimmed)) {
            return true;
        }

        return false;
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
    private function wrapParagraph(string $inlineHtml, ?string $style, string $rawText, ?string $alignment): string
    {
        if ($this->isSceneBreak($style, $rawText, $alignment)) {
            return '<hr>';
        }

        if ($this->isBlockquote($style)) {
            return '<blockquote><p>'.$inlineHtml.'</p></blockquote>';
        }

        return '<p>'.$inlineHtml.'</p>';
    }

    /**
     * Determine whether a paragraph is a chapter heading.
     */
    private function isChapterHeading(?string $style = null, string $text = ''): bool
    {
        if ($style !== null && preg_match('/^(Heading1|Heading2|heading\s*[12])$/i', $style)) {
            return true;
        }

        return (bool) preg_match('/^(chapter|kapitel|teil)\s+\w+/i', trim($text));
    }

    /**
     * Extract a clean chapter title from heading text.
     */
    private function extractTitle(string $text): string
    {
        $text = trim($text);

        if (preg_match('/^(?:chapter|kapitel|teil)\s+\w+\s*[:\-—–.]\s*(.+)$/i', $text, $matches)) {
            return trim($matches[1]);
        }

        return $text;
    }

    /**
     * Split paragraphs into chapters based on heading detection.
     *
     * @param  list<array{style: string|null, text: string, html: string}>  $paragraphs
     * @return list<array{number: int, title: string, word_count: int, content: string}>
     */
    private function splitIntoChapters(array $paragraphs): array
    {
        $chapters = [];
        $currentTitle = null;
        $currentContent = [];

        foreach ($paragraphs as $para) {
            if ($this->isChapterHeading($para['style'], $para['text'])) {
                if ($currentTitle !== null) {
                    $chapters[] = $this->buildChapter(count($chapters) + 1, $currentTitle, $currentContent);
                }
                $currentTitle = $this->extractTitle($para['text']);
                $currentContent = [];
            } else {
                $currentContent[] = $para['html'];
            }
        }

        if ($currentTitle !== null) {
            $chapters[] = $this->buildChapter(count($chapters) + 1, $currentTitle, $currentContent);
        }

        return $this->filterAndRenumber($chapters);
    }

    /**
     * Remove chapters with empty/whitespace-only content and renumber sequentially.
     *
     * @param  list<array{number: int, title: string, word_count: int, content: string}>  $chapters
     * @return list<array{number: int, title: string, word_count: int, content: string}>
     */
    private function filterAndRenumber(array $chapters): array
    {
        $filtered = array_values(array_filter(
            $chapters,
            fn (array $chapter): bool => trim(strip_tags($chapter['content'])) !== '',
        ));

        foreach ($filtered as $i => &$chapter) {
            $chapter['number'] = $i + 1;
        }

        return $filtered;
    }

    /**
     * Build a chapter array from collected content.
     *
     * @param  list<string>  $contentParagraphs
     * @return array{number: int, title: string, word_count: int, content: string}
     */
    private function buildChapter(int $number, string $title, array $contentParagraphs): array
    {
        $content = implode('', $contentParagraphs);

        return [
            'number' => $number,
            'title' => $title,
            'word_count' => str_word_count(strip_tags($content)),
            'content' => $content,
        ];
    }

    /**
     * Fallback when no chapters are detected — return the entire document as one chapter.
     *
     * @return array{chapters: list<array{number: int, title: string, word_count: int, content: string}>}
     */
    private function fallbackResult(UploadedFile $file): array
    {
        $xml = $this->extractDocumentXml($file);

        $content = '';
        if ($xml !== null) {
            $paragraphs = $this->extractParagraphs($xml);
            $content = implode('', array_column($paragraphs, 'html'));
        }

        return [
            'chapters' => [
                [
                    'number' => 1,
                    'title' => 'Full Document',
                    'word_count' => str_word_count(strip_tags($content)),
                    'content' => $content,
                ],
            ],
        ];
    }
}
