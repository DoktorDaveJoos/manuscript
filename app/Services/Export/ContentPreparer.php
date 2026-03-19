<?php

namespace App\Services\Export;

class ContentPreparer
{
    /**
     * Convert HTML content to plain text, preserving paragraph and scene breaks.
     */
    public function toPlainText(string $html): string
    {
        return strip_tags(str_replace(
            ['<p>', '</p>', '<br>', '<br/>', '<br />', '<hr>', '<hr/>', '<hr />'],
            ["\n", "\n", "\n", "\n", "\n", "\n* * *\n", "\n* * *\n", "\n* * *\n"],
            $html,
        ));
    }

    /**
     * Convert TipTap HTML to valid XHTML for EPUB.
     */
    public function toXhtml(string $html): string
    {
        $html = $this->normalizeHtml($html);

        // Convert <hr> variants to self-closing XHTML
        $html = preg_replace('/<hr\s*\/?>/', '<hr class="scene-break" />', $html);

        // Convert <br> variants to self-closing XHTML
        $html = preg_replace('/<br\s*\/?>/', '<br />', $html);

        $html = trim($html);

        return $html;
    }

    /**
     * Prepare HTML for chapter rendering (PDF and Chromium-based exports).
     */
    public function toChapterHtml(string $html): string
    {
        $html = $this->normalizeHtml($html);

        // Convert <hr> to styled scene break (spaced asterisks matching preview)
        $html = preg_replace(
            '/<hr\s*\/?>/',
            '<p class="scene-break">*&nbsp;&nbsp;*&nbsp;&nbsp;*</p>',
            $html,
        );

        return $html;
    }

    /**
     * Backward-compatible alias for toChapterHtml().
     */
    public function toPdfHtml(string $html): string
    {
        return $this->toChapterHtml($html);
    }

    /**
     * Add a drop cap to the first letter of the first paragraph.
     */
    public function addDropCap(string $html): string
    {
        return preg_replace(
            '/(<p[^>]*>)(\s*)([a-zA-Z\x{00C0}-\x{024F}])/u',
            '$1$2<span class="drop-cap">$3</span>',
            $html,
            1,
        );
    }

    /**
     * Convert plain text (from AppSetting) to <p> tags for mPDF.
     */
    public function toMatterHtml(string $plainText): string
    {
        return $this->plainTextToParagraphs($plainText, ENT_HTML5, 'matter-body');
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
