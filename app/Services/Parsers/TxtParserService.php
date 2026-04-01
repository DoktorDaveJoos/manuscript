<?php

namespace App\Services\Parsers;

use App\Contracts\DocumentParserInterface;
use App\Services\Parsers\Concerns\DetectsChapters;
use Illuminate\Http\UploadedFile;

class TxtParserService implements DocumentParserInterface
{
    use DetectsChapters;

    /**
     * Parse a .txt file and extract chapters by heading detection.
     *
     * @return array{chapters: list<array{number: int, title: string, word_count: int, content: string}>}
     */
    public function parse(UploadedFile $file): array
    {
        $raw = $this->normalizeLineEndings($file->get());

        $encoding = $this->detectEncoding($raw);
        if ($encoding !== 'UTF-8') {
            $raw = mb_convert_encoding($raw, 'UTF-8', $encoding);
        }

        $blocks = preg_split('/\n{2,}/', $raw);

        $paragraphs = [];
        foreach ($blocks as $block) {
            $text = trim($block);
            if ($text === '') {
                continue;
            }

            if ($this->isSceneBreak(null, $text)) {
                $paragraphs[] = [
                    'style' => null,
                    'text' => $text,
                    'html' => '<hr>',
                ];

                continue;
            }

            $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            $paragraphs[] = [
                'style' => null,
                'text' => $text,
                'html' => '<p>'.$escaped.'</p>',
            ];
        }

        $chapters = $this->splitIntoChapters($paragraphs);

        if (count($chapters) === 0) {
            return $this->fallbackSingleChapter($paragraphs);
        }

        return ['chapters' => $chapters];
    }

    /**
     * Detect the encoding of raw file content, distinguishing Windows-1252 from ISO-8859-1.
     *
     * mb_detect_encoding cannot distinguish Windows-1252 from ISO-8859-1 because
     * ISO-8859-1 is a strict superset at the byte level. Bytes 0x80-0x9F are
     * undefined control characters in ISO-8859-1 but printable characters in
     * Windows-1252 (smart quotes, em-dash, etc.).
     */
    private function detectEncoding(string $raw): string
    {
        $encoding = mb_detect_encoding($raw, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);

        if ($encoding === 'ISO-8859-1' && preg_match('/[\x80-\x9F]/', $raw)) {
            return 'Windows-1252';
        }

        return $encoding ?: 'UTF-8';
    }
}
