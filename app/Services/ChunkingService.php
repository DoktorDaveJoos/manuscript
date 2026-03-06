<?php

namespace App\Services;

use App\Models\ChapterVersion;
use App\Models\Chunk;
use Illuminate\Support\Collection;

class ChunkingService
{
    private const TARGET_WORDS = 500;

    private const OVERLAP_WORDS = 50;

    /**
     * Split HTML content into overlapping text chunks of approximately TARGET_WORDS.
     *
     * @return array<int, array{content: string, position: int}>
     */
    public function chunk(string $html): array
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', trim($text));

        if ($text === '') {
            return [];
        }

        $words = preg_split('/\s+/', $text);
        $totalWords = count($words);

        if ($totalWords <= self::TARGET_WORDS) {
            return [['content' => $text, 'position' => 0]];
        }

        $chunks = [];
        $position = 0;
        $offset = 0;

        while ($offset < $totalWords) {
            $end = min($offset + self::TARGET_WORDS, $totalWords);
            $chunkWords = array_slice($words, $offset, $end - $offset);
            $chunks[] = [
                'content' => implode(' ', $chunkWords),
                'position' => $position,
            ];
            $position++;

            // Move forward by (TARGET_WORDS - OVERLAP_WORDS) to create overlap
            $offset += self::TARGET_WORDS - self::OVERLAP_WORDS;
        }

        return $chunks;
    }

    /**
     * Chunk a chapter version's content and persist as Chunk models.
     *
     * @return Collection<int, Chunk>
     */
    public function chunkVersion(ChapterVersion $chapterVersion): Collection
    {
        $chapterVersion->chunks()->delete();

        $content = $chapterVersion->content ?? '';
        $rawChunks = $this->chunk($content);

        $chunks = collect();

        foreach ($rawChunks as $raw) {
            $chunk = $chapterVersion->chunks()->create([
                'content' => $raw['content'],
                'position' => $raw['position'],
            ]);
            $chunks->push($chunk);
        }

        return $chunks;
    }
}
