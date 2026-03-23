<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Scene;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function search(Request $request, Book $book): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|min:1|max:500',
            'case_sensitive' => 'boolean',
            'whole_word' => 'boolean',
            'regex' => 'boolean',
        ]);

        $query = $validated['query'];
        $caseSensitive = $validated['case_sensitive'] ?? false;
        $wholeWord = $validated['whole_word'] ?? false;
        $useRegex = $validated['regex'] ?? false;

        $pattern = $this->buildPattern($query, $caseSensitive, $wholeWord, $useRegex);
        if ($pattern === null) {
            return response()->json(['results' => [], 'total_matches' => 0, 'chapter_count' => 0]);
        }

        $chapterIds = $book->chapters()->pluck('id');

        $scenes = Scene::whereIn('chapter_id', $chapterIds)
            ->with(['chapter:id,title,reader_order,storyline_id'])
            ->orderBy('chapter_id')
            ->orderBy('sort_order')
            ->get(['id', 'chapter_id', 'title', 'content', 'sort_order']);

        $grouped = [];
        $totalMatches = 0;

        foreach ($scenes as $scene) {
            $plainText = strip_tags($scene->content ?? '');

            if ($plainText === '') {
                continue;
            }

            $matches = $this->findMatches($plainText, $pattern);

            if (empty($matches)) {
                continue;
            }

            $chapterId = $scene->chapter_id;

            if (! isset($grouped[$chapterId])) {
                $grouped[$chapterId] = [
                    'chapter_id' => $chapterId,
                    'chapter_title' => $scene->chapter->title,
                    'reader_order' => $scene->chapter->reader_order,
                    'matches' => [],
                ];
            }

            foreach ($matches as $match) {
                $context = $this->extractContext($plainText, $match['start'], $match['length']);
                $grouped[$chapterId]['matches'][] = [
                    'scene_id' => $scene->id,
                    'scene_title' => $scene->title,
                    'context' => $context,
                    'match_start' => $match['start'],
                    'match_length' => $match['length'],
                ];
                $totalMatches++;
            }
        }

        $results = collect($grouped)->sortBy('reader_order')->values()->all();

        return response()->json([
            'results' => $results,
            'total_matches' => $totalMatches,
            'chapter_count' => count($results),
        ]);
    }

    public function replaceAll(Request $request, Book $book): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'required|string|min:1|max:500',
            'replace' => 'nullable|string|max:500',
            'case_sensitive' => 'boolean',
            'whole_word' => 'boolean',
            'regex' => 'boolean',
        ]);

        $search = $validated['search'];
        $replace = $validated['replace'] ?? '';
        $caseSensitive = $validated['case_sensitive'] ?? false;
        $wholeWord = $validated['whole_word'] ?? false;
        $useRegex = $validated['regex'] ?? false;

        $pattern = $this->buildPattern($search, $caseSensitive, $wholeWord, $useRegex);
        if ($pattern === null) {
            return response()->json(['replaced_count' => 0, 'affected_scenes' => 0]);
        }

        $chapterIds = $book->chapters()->pluck('id');

        $scenes = Scene::whereIn('chapter_id', $chapterIds)
            ->get(['id', 'chapter_id', 'content']);

        $replacedCount = 0;
        $affectedSceneIds = [];
        $affectedChapterIds = [];

        DB::transaction(function () use ($scenes, $pattern, $replace, &$replacedCount, &$affectedSceneIds, &$affectedChapterIds) {
            foreach ($scenes as $scene) {
                if (empty($scene->content)) {
                    continue;
                }

                $result = $this->replaceInHtml($scene->content, $pattern, $replace);

                if ($result['count'] > 0) {
                    $wordCount = str_word_count(strip_tags($result['content']));
                    $scene->update([
                        'content' => $result['content'],
                        'word_count' => $wordCount,
                    ]);
                    $replacedCount += $result['count'];
                    $affectedSceneIds[] = $scene->id;
                    $affectedChapterIds[] = $scene->chapter_id;
                }
            }
        });

        $book->chapters()
            ->whereIn('id', array_unique($affectedChapterIds))
            ->get()
            ->each
            ->recalculateWordCount();

        return response()->json([
            'replaced_count' => $replacedCount,
            'affected_scenes' => count($affectedSceneIds),
        ]);
    }

    /**
     * Build a regex pattern from search options. Returns null if the regex is invalid.
     */
    private function buildPattern(string $query, bool $caseSensitive, bool $wholeWord, bool $useRegex): ?string
    {
        $flags = ($caseSensitive ? '' : 'i').'u';

        if ($useRegex) {
            $pattern = '/'.$query.'/'.$flags;

            return @preg_match($pattern, '') !== false ? $pattern : null;
        }

        $escaped = preg_quote($query, '/');

        if ($wholeWord) {
            return '/\b'.$escaped.'\b/'.$flags;
        }

        return '/'.$escaped.'/'.$flags;
    }

    /**
     * Find all match positions in plain text.
     *
     * @return array<int, array{start: int, length: int}>
     */
    private function findMatches(string $text, string $pattern): array
    {
        if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }

        return array_map(fn ($m) => [
            'start' => mb_strlen(substr($text, 0, $m[1])),
            'length' => mb_strlen($m[0]),
        ], $matches[0]);
    }

    /**
     * Extract surrounding context for a match.
     */
    private function extractContext(string $text, int $start, int $length, int $contextChars = 30): string
    {
        $before = mb_substr($text, max(0, $start - $contextChars), min($start, $contextChars));
        $match = mb_substr($text, $start, $length);
        $after = mb_substr($text, $start + $length, $contextChars);

        $prefix = $start > $contextChars ? '...' : '';
        $suffix = ($start + $length + $contextChars) < mb_strlen($text) ? '...' : '';

        return $prefix.$before.$match.$after.$suffix;
    }

    /**
     * Replace search term in HTML content, only within text nodes.
     *
     * @return array{content: string, count: int}
     */
    private function replaceInHtml(string $html, string $pattern, string $replace): array
    {
        $totalCount = 0;

        $result = preg_replace_callback('/(?<=>)([^<]+)(?=<)|^([^<]+)/', function ($matches) use ($pattern, $replace, &$totalCount) {
            $textContent = $matches[0];
            $replaced = @preg_replace($pattern, $replace, $textContent, -1, $count);

            if ($replaced === null) {
                return $textContent;
            }

            $totalCount += $count;

            return $replaced;
        }, $html);

        return [
            'content' => $result ?? $html,
            'count' => $totalCount,
        ];
    }
}
