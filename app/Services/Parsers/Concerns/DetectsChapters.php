<?php

namespace App\Services\Parsers\Concerns;

trait DetectsChapters
{
    /**
     * Determine whether a paragraph is a chapter heading.
     */
    protected function isChapterHeading(?string $style, string $text): bool
    {
        if ($style !== null && preg_match('/^(Heading1|Heading2|heading\s*[12])$/i', $style)) {
            return true;
        }

        return (bool) preg_match('/^(chapter|kapitel|teil)\s+\w+/i', trim($text));
    }

    /**
     * Detect if a paragraph is a scene break.
     */
    protected function isSceneBreak(?string $style, string $text, ?string $alignment = null): bool
    {
        if ($style !== null && preg_match('/^(Separator|SceneBreak|Divider)$/i', $style)) {
            return true;
        }

        $trimmed = trim($text);
        if ($trimmed !== '' && preg_match('/^[\*\#\~\-\x{2014}\x{2013}\s]{3,20}$/u', $trimmed) && preg_match('/[\*\#\~\-\x{2014}\x{2013}]/u', $trimmed)) {
            return true;
        }

        if ($alignment === 'center' && mb_strlen($trimmed) <= 10 && $trimmed !== '' && preg_match('/^[\p{P}\p{S}\s]+$/u', $trimmed)) {
            return true;
        }

        return false;
    }

    /**
     * Extract a clean chapter title from heading text.
     */
    protected function extractTitle(string $text): string
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
    protected function splitIntoChapters(array $paragraphs): array
    {
        $chapters = [];
        $currentTitle = null;
        $currentContent = [];

        foreach ($paragraphs as $para) {
            if ($this->isChapterHeading($para['style'] ?? null, $para['text'])) {
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
     * Build a chapter array from collected content.
     *
     * @param  list<string>  $contentParagraphs
     * @return array{number: int, title: string, word_count: int, content: string}
     */
    protected function buildChapter(int $number, string $title, array $contentParagraphs): array
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
     * Remove chapters with empty/whitespace-only content and renumber sequentially.
     *
     * @param  list<array{number: int, title: string, word_count: int, content: string}>  $chapters
     * @return list<array{number: int, title: string, word_count: int, content: string}>
     */
    protected function filterAndRenumber(array $chapters): array
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
     * Normalize line endings to \n.
     */
    protected function normalizeLineEndings(string $raw): string
    {
        return str_replace(["\r\n", "\r"], "\n", $raw);
    }

    /**
     * Fallback when no chapters are detected — return the entire document as one chapter.
     *
     * @param  list<array{style: string|null, text: string, html: string}>  $paragraphs
     * @return array{chapters: list<array{number: int, title: string, word_count: int, content: string}>}
     */
    protected function fallbackSingleChapter(array $paragraphs): array
    {
        $content = implode('', array_column($paragraphs, 'html'));

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
