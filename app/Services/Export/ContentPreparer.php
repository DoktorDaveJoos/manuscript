<?php

namespace App\Services\Export;

use App\Enums\SceneBreakStyle;

class ContentPreparer
{
    /**
     * Convert HTML content to plain text, preserving paragraph and scene breaks.
     */
    public function toPlainText(string $html, ?SceneBreakStyle $sceneBreak = null): string
    {
        $sceneBreak = $sceneBreak ?? SceneBreakStyle::Asterisks;
        $plainBreak = $sceneBreak->plainText();

        return strip_tags(str_replace(
            ['<p>', '</p>', '<br>', '<br/>', '<br />', '<hr>', '<hr/>', '<hr />'],
            ["\n", "\n", "\n", "\n", "\n", "\n{$plainBreak}\n", "\n{$plainBreak}\n", "\n{$plainBreak}\n"],
            $html,
        ));
    }

    /**
     * Convert TipTap HTML to valid XHTML for EPUB.
     */
    public function toXhtml(string $html, ?SceneBreakStyle $sceneBreak = null): string
    {
        $sceneBreak = $sceneBreak ?? SceneBreakStyle::Asterisks;
        $html = $this->normalizeHtml($html);

        // Convert <hr> variants using the chosen scene break style
        $html = preg_replace('/<hr\s*\/?>/', $sceneBreak->xhtml(), $html);

        // Convert <br> variants to self-closing XHTML
        $html = preg_replace('/<br\s*\/?>/', '<br />', $html);

        $html = trim($html);

        return $html;
    }

    /**
     * Prepare HTML for chapter rendering (PDF and Chromium-based exports).
     */
    public function toChapterHtml(string $html, ?SceneBreakStyle $sceneBreak = null): string
    {
        $sceneBreak = $sceneBreak ?? SceneBreakStyle::Asterisks;
        $html = $this->normalizeHtml($html);

        // Convert <hr> using the chosen scene break style
        $html = preg_replace('/<hr\s*\/?>/', $sceneBreak->html(), $html);

        return $html;
    }

    /**
     * Backward-compatible alias for toChapterHtml().
     */
    public function toPdfHtml(string $html, ?SceneBreakStyle $sceneBreak = null): string
    {
        return $this->toChapterHtml($html, $sceneBreak);
    }

    /**
     * Add a drop cap to the first letter of the first non-empty paragraph,
     * capturing any leading punctuation (quotes, brackets) into the drop cap span.
     */
    public function addDropCap(string $html): string
    {
        // Match first <p> with content, capturing leading punctuation + first letter
        $pattern = '/(<p[^>]*>)([\s]*)(["\'"\'\x{201C}\x{201D}\x{2018}\x{2019}\x{00AB}\x{00BF}\x{00A1}\(\[]*)([\p{L}\p{N}])/u';

        return preg_replace_callback(
            $pattern,
            function ($matches) {
                $openTag = $matches[1];
                $whitespace = $matches[2];
                $punctuation = $matches[3];
                $letter = $matches[4];

                $dropCapContent = $punctuation.$letter;

                return "{$openTag}{$whitespace}<span class=\"drop-cap\">{$dropCapContent}</span>";
            },
            $html,
            1,
        );
    }

    /**
     * Parse HTML into structured segments with formatting metadata for PhpWord.
     *
     * @return array<int, array{type: string, text?: string, bold?: bool, italic?: bool, strikethrough?: bool}>
     */
    public function toFormattedSegments(string $html): array
    {
        $segments = [];
        $dom = new \DOMDocument;
        @$dom->loadHTML('<body>'.mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8').'</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $body = $dom->getElementsByTagName('body')->item(0);
        if (! $body) {
            return $segments;
        }

        foreach ($body->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            if ($child->nodeName === 'hr') {
                $segments[] = ['type' => 'scene-break'];

                continue;
            }

            if (in_array($child->nodeName, ['p', 'blockquote'])) {
                if (trim($child->textContent) === '') {
                    continue;
                }

                $segments[] = ['type' => 'paragraph-start'];
                $this->extractTextSegments($child, $segments, [
                    'bold' => false,
                    'italic' => $child->nodeName === 'blockquote',
                    'strikethrough' => false,
                ]);
            }
        }

        return $segments;
    }

    /**
     * Convert plain text (from AppSetting) to <p> tags for mPDF.
     */
    public function toMatterHtml(string $plainText, string $class = 'matter-body'): string
    {
        return $this->plainTextToParagraphs($plainText, ENT_HTML5, $class);
    }

    /**
     * Convert plain text (from AppSetting) to XHTML-compliant <p> tags for EPUB.
     */
    public function toMatterXhtml(string $plainText): string
    {
        return $this->plainTextToParagraphs($plainText, ENT_XML1);
    }

    /**
     * Convert plain text to <p> tags with configurable encoding and optional class.
     */
    private function plainTextToParagraphs(string $plainText, int $encoding, ?string $class = null): string
    {
        if (trim($plainText) === '') {
            return '';
        }

        $lines = preg_split('/\r?\n/', trim($plainText));
        $classAttr = $class !== null ? " class=\"{$class}\"" : '';

        return implode("\n", array_map(
            fn (string $line) => "<p{$classAttr}>".htmlspecialchars($line, $encoding, 'UTF-8').'</p>',
            array_filter($lines, fn (string $line) => trim($line) !== ''),
        ));
    }

    /**
     * Recursively extract text segments with formatting from a DOM node.
     *
     * @param  array<int, array{type: string, text?: string, bold?: bool, italic?: bool, strikethrough?: bool}>  $segments
     * @param  array{bold: bool, italic: bool, strikethrough: bool}  $formatting
     */
    private function extractTextSegments(\DOMNode $node, array &$segments, array $formatting): void
    {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text = $child->textContent;
                if ($text !== '') {
                    $segments[] = array_merge(['type' => 'text', 'text' => $text], $formatting);
                }
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $childFormatting = $formatting;
                match ($child->nodeName) {
                    'strong', 'b' => $childFormatting['bold'] = true,
                    'em', 'i' => $childFormatting['italic'] = true,
                    's', 'del' => $childFormatting['strikethrough'] = true,
                    'p' => null,
                    default => null,
                };
                $this->extractTextSegments($child, $segments, $childFormatting);
            }
        }
    }

    /**
     * Normalize HTML from TipTap editor.
     */
    private function normalizeHtml(string $html): string
    {
        // Remove empty paragraphs
        $html = preg_replace('/<p>\s*<\/p>/', '', $html);

        // Normalize whitespace within tags
        $html = preg_replace('/\s+/', ' ', $html);

        return trim($html);
    }
}
