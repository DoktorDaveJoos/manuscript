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
     * Prepare HTML for mPDF consumption.
     */
    public function toPdfHtml(string $html): string
    {
        $html = $this->normalizeHtml($html);

        // Convert <hr> to styled scene break
        $html = preg_replace(
            '/<hr\s*\/?>/',
            '<p class="scene-break">* * *</p>',
            $html,
        );

        return $html;
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
