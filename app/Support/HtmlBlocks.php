<?php

namespace App\Support;

use Dom\HTMLDocument;

class HtmlBlocks
{
    /**
     * Split an HTML fragment into its top-level blocks, preserving each
     * block's markup byte-identically. `<hr>` elements (scene-break
     * markers) are dropped; whitespace between blocks is ignored.
     *
     * @return array<int, string>
     */
    public static function split(?string $html): array
    {
        if ($html === null || trim($html) === '') {
            return [];
        }

        $document = HTMLDocument::createFromString("<body>{$html}</body>", LIBXML_NOERROR);

        $blocks = [];

        foreach ($document->body->childNodes as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                if (trim($node->textContent) !== '') {
                    $blocks[] = $node->textContent;
                }

                continue;
            }

            if ($node->nodeType !== XML_ELEMENT_NODE || $node->nodeName === 'HR') {
                continue;
            }

            $blocks[] = $document->saveHtml($node);
        }

        return $blocks;
    }
}
