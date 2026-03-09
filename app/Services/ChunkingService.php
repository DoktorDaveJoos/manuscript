<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Chunk;
use Illuminate\Support\Collection;

class ChunkingService
{
    private const TARGET_WORDS = 500;

    private const OVERLAP_WORDS = 50;

    private const MAX_SCENE_CHUNK_WORDS = 800;

    /**
     * Split HTML content into overlapping text chunks of approximately TARGET_WORDS.
     *
     * @return array<int, array{content: string, position: int}>
     */
    public function chunk(string $html): array
    {
        $text = $this->stripHtml($html);

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
     * Chunk a chapter's scenes into text chunks using scene boundaries.
     *
     * Scenes under MAX_SCENE_CHUNK_WORDS become a single chunk.
     * Larger scenes are split on paragraph boundaries (\n\n or </p>).
     *
     * @return array<int, array{content: string, position: int, scene_id: int}>
     */
    public function chunkByScenes(Chapter $chapter): array
    {
        $scenes = $chapter->relationLoaded('scenes')
            ? $chapter->scenes->sortBy('sort_order')
            : $chapter->scenes()->orderBy('sort_order')->get();

        if ($scenes->isEmpty()) {
            return [];
        }

        $chunks = [];
        $position = 0;

        foreach ($scenes as $scene) {
            $text = $this->stripHtml($scene->content ?? '');

            if ($text === '') {
                continue;
            }

            $wordCount = count(preg_split('/\s+/', $text));

            if ($wordCount <= self::MAX_SCENE_CHUNK_WORDS) {
                $chunks[] = [
                    'content' => $text,
                    'position' => $position,
                    'scene_id' => $scene->id,
                ];
                $position++;
            } else {
                foreach ($this->splitOnParagraphs($text) as $paragraphChunk) {
                    $chunks[] = [
                        'content' => $paragraphChunk,
                        'position' => $position,
                        'scene_id' => $scene->id,
                    ];
                    $position++;
                }
            }
        }

        return $chunks;
    }

    /**
     * Split text on paragraph boundaries, merging small paragraphs together
     * and splitting huge paragraphs by word count.
     *
     * @return array<int, string>
     */
    private function splitOnParagraphs(string $text): array
    {
        $paragraphs = preg_split('/\n\s*\n/', $text);
        $paragraphs = array_filter(array_map('trim', $paragraphs));

        if (empty($paragraphs)) {
            return [$text];
        }

        $chunks = [];
        $buffer = '';
        $bufferWords = 0;

        foreach ($paragraphs as $paragraph) {
            $paraWords = count(preg_split('/\s+/', $paragraph));

            // If a single paragraph exceeds the limit, split by words
            if ($paraWords > self::MAX_SCENE_CHUNK_WORDS) {
                if ($buffer !== '') {
                    $chunks[] = $buffer;
                    $buffer = '';
                    $bufferWords = 0;
                }

                foreach ($this->splitByWords($paragraph) as $wordChunk) {
                    $chunks[] = $wordChunk;
                }

                continue;
            }

            if ($bufferWords + $paraWords > self::MAX_SCENE_CHUNK_WORDS && $buffer !== '') {
                $chunks[] = $buffer;
                $buffer = '';
                $bufferWords = 0;
            }

            $buffer = $buffer === '' ? $paragraph : $buffer."\n\n".$paragraph;
            $bufferWords += $paraWords;
        }

        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return $chunks;
    }

    /**
     * Split text by word count as a last-resort fallback.
     *
     * @return array<int, string>
     */
    private function splitByWords(string $text): array
    {
        $words = preg_split('/\s+/', $text);
        $totalWords = count($words);
        $chunks = [];
        $offset = 0;

        while ($offset < $totalWords) {
            $end = min($offset + self::MAX_SCENE_CHUNK_WORDS, $totalWords);
            $chunks[] = implode(' ', array_slice($words, $offset, $end - $offset));
            $offset = $end;
        }

        return $chunks;
    }

    /**
     * Chunk a chapter version's content and persist as Chunk models.
     * Uses scene-based chunking when the chapter has scenes, falling back to word-based.
     *
     * @return Collection<int, Chunk>
     */
    public function chunkVersion(ChapterVersion $chapterVersion, ?Chapter $chapter = null): Collection
    {
        $chapterVersion->chunks()->delete();

        $rawChunks = $chapter ? $this->chunkByScenes($chapter) : [];

        if (empty($rawChunks)) {
            $rawChunks = $this->chunk($chapterVersion->content ?? '');
        }

        $chunks = collect();

        foreach ($rawChunks as $raw) {
            $chunk = $chapterVersion->chunks()->create([
                'content' => $raw['content'],
                'position' => $raw['position'],
                'scene_id' => $raw['scene_id'] ?? null,
            ]);
            $chunks->push($chunk);
        }

        return $chunks;
    }

    /**
     * Strip HTML tags and normalize whitespace.
     */
    private function stripHtml(string $html): string
    {
        // Replace </p> and <br> with newlines before stripping to preserve paragraph boundaries
        $text = preg_replace('/<\/p>\s*<p[^>]*>/i', "\n\n", $html);
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Normalize spaces within lines but preserve paragraph breaks
        $text = preg_replace('/[^\S\n]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }
}
