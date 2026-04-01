<?php

namespace App\Services\Parsers\Concerns;

use App\Support\WordCount;

trait DetectsChapters
{
    /** Chapter keywords that require a number/numeral after them. */
    private const NUMBERED_HEADING_KEYWORDS = 'chapter|kapitel|teil|chapitre|capítulo|capitolo|part|act|book|section';

    /** Number-like tokens that follow a numbered heading keyword. */
    private const HEADING_NUMBER_PATTERN = '\d+|[IVXLC]+\b|one|two|three|four|five|six|seven|eight|nine|ten|eins|zwei|drei|vier|fünf|sechs|sieben|acht|neun|zehn';

    /** Chapter keywords that stand alone as the entire heading. */
    private const STANDALONE_HEADING_KEYWORDS = 'prologue|epilogue|introduction|foreword|afterword|preface|acknowledgements|acknowledgments';

    /**
     * Determine whether a paragraph is a chapter heading.
     */
    protected function isChapterHeading(?string $style, string $text): bool
    {
        if ($style !== null && preg_match('/^(Heading\s*[12]|Überschrift\s*[12]|Titre\s*[12]|Título\s*[12]|Intestazione\s*[12])$/iu', $style)) {
            return true;
        }

        $trimmed = trim($text);

        if (preg_match('/^('.self::NUMBERED_HEADING_KEYWORDS.')\s+('.self::HEADING_NUMBER_PATTERN.')/iu', $trimmed)) {
            return true;
        }

        if (preg_match('/^('.self::STANDALONE_HEADING_KEYWORDS.')$/iu', $trimmed)) {
            return true;
        }

        return false;
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
        if ($trimmed !== '' && preg_match('/^[\*\#\~\-\x{2014}\x{2013}\s]{3,20}$/u', $trimmed)) {
            $separatorCount = preg_match_all('/[\*\#\~\-\x{2014}\x{2013}]/u', $trimmed);
            if ($separatorCount >= 3) {
                return true;
            }
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

        if (preg_match('/^(?:'.self::NUMBERED_HEADING_KEYWORDS.')\s+(?:'.self::HEADING_NUMBER_PATTERN.')\s*[:\-—–.]\s*(.+)$/iu', $text, $matches)) {
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
        $preambleContent = [];

        foreach ($paragraphs as $para) {
            if ($this->isChapterHeading($para['style'] ?? null, $para['text'])) {
                if ($currentTitle !== null) {
                    $chapters[] = $this->buildChapter(count($chapters) + 1, $currentTitle, $currentContent);
                }
                $currentTitle = $this->extractTitle($para['text']);
                $currentContent = [];
            } elseif ($currentTitle === null) {
                $preambleContent[] = $para['html'];
            } else {
                $currentContent[] = $para['html'];
            }
        }

        if ($currentTitle !== null) {
            $chapters[] = $this->buildChapter(count($chapters) + 1, $currentTitle, $currentContent);
        }

        if ($preambleContent !== [] && $chapters !== []) {
            array_unshift($chapters, $this->buildChapter(1, 'Preamble', $preambleContent));
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
            'word_count' => WordCount::count($content),
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
                    'word_count' => WordCount::count($content),
                    'content' => $content,
                ],
            ],
        ];
    }
}
