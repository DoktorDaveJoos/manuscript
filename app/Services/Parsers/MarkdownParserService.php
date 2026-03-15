<?php

namespace App\Services\Parsers;

use App\Contracts\DocumentParserInterface;
use App\Services\Parsers\Concerns\DetectsChapters;
use Illuminate\Http\UploadedFile;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;

class MarkdownParserService implements DocumentParserInterface
{
    use DetectsChapters;

    /**
     * Parse a Markdown file and extract chapters by # and ## headings.
     *
     * @return array{chapters: list<array{number: int, title: string, word_count: int, content: string}>}
     */
    public function parse(UploadedFile $file): array
    {
        $raw = $this->normalizeLineEndings($file->get());

        // Split on # and ## headings (### and deeper are kept as content)
        $sections = preg_split('/^(#{1,2})\s+(.+)$/m', $raw, -1, PREG_SPLIT_DELIM_CAPTURE);

        $converter = $this->createConverter();

        // First element is content before any heading
        $preamble = trim($sections[0] ?? '');

        $chapters = [];

        // Process heading-body pairs (groups of 3: marker, title, body)
        for ($i = 1; $i + 2 <= count($sections); $i += 3) {
            $headingTitle = trim($sections[$i + 1]);
            $body = trim($sections[$i + 2] ?? '');

            $html = '';
            if ($body !== '') {
                $html = trim($converter->convert($body)->getContent());
            }

            $chapters[] = $this->buildChapter(
                count($chapters) + 1,
                $this->extractTitle($headingTitle),
                $html !== '' ? [$html] : [],
            );
        }

        $chapters = $this->filterAndRenumber($chapters);

        // Fallback: no headings found — entire file as single chapter
        if (count($chapters) === 0) {
            $html = $preamble !== '' ? trim($converter->convert($raw)->getContent()) : '';

            return $this->fallbackSingleChapter(
                $html !== '' ? [['style' => null, 'text' => '', 'html' => $html]] : [],
            );
        }

        return ['chapters' => $chapters];
    }

    /**
     * Create a CommonMark converter with safe defaults.
     */
    private function createConverter(): MarkdownConverter
    {
        $environment = new Environment([
            'html_input' => 'strip',
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);

        return new MarkdownConverter($environment);
    }
}
