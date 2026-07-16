<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Scene;
use App\Support\WordCount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    public function search(Request $request, Book $book): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:1', 'max:500'],
            'case_sensitive' => ['boolean'],
            'whole_word' => ['boolean'],
            'regex' => ['boolean'],
        ]);

        $query = $this->normalizeQuery($validated['query']);
        if ($query === '') {
            return response()->json([
                'message' => __('The search query must not be empty.'),
                'errors' => ['query' => [__('The search query must not be empty.')]],
            ], 422);
        }

        $caseSensitive = $validated['case_sensitive'] ?? false;
        $wholeWord = $validated['whole_word'] ?? false;
        $useRegex = $validated['regex'] ?? false;

        $pattern = $this->buildPattern($query, $caseSensitive, $wholeWord, $useRegex);
        if ($pattern === null) {
            return $this->invalidRegexResponse();
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
            $plainText = $this->htmlToSearchableText($scene->content ?? '');

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
            'search' => ['required', 'string', 'min:1', 'max:500'],
            'replace' => ['nullable', 'string', 'max:500'],
            'case_sensitive' => ['boolean'],
            'whole_word' => ['boolean'],
            'regex' => ['boolean'],
        ]);

        $search = $this->normalizeQuery($validated['search']);
        if ($search === '') {
            return response()->json([
                'message' => __('The search query must not be empty.'),
                'errors' => ['search' => [__('The search query must not be empty.')]],
            ], 422);
        }

        $replace = $validated['replace'] ?? '';
        $caseSensitive = $validated['case_sensitive'] ?? false;
        $wholeWord = $validated['whole_word'] ?? false;
        $useRegex = $validated['regex'] ?? false;

        $pattern = $this->buildPattern($search, $caseSensitive, $wholeWord, $useRegex);
        if ($pattern === null) {
            return $this->invalidRegexResponse();
        }

        $chapterIds = $book->chapters()->pluck('id');

        $scenes = Scene::whereIn('chapter_id', $chapterIds)
            ->get(['id', 'chapter_id', 'content', 'content_version']);

        $replacedCount = 0;
        $affectedSceneIds = [];
        $affectedChapterIds = [];

        DB::transaction(function () use ($scenes, $pattern, $replace, $useRegex, &$replacedCount, &$affectedSceneIds, &$affectedChapterIds) {
            foreach ($scenes as $scene) {
                if (empty($scene->content)) {
                    continue;
                }

                $result = $this->replaceInHtml($scene->content, $pattern, $replace, $useRegex);

                if ($result['count'] > 0) {
                    $wordCount = WordCount::count($result['content']);
                    $scene->update([
                        'content' => $result['content'],
                        'word_count' => $wordCount,
                        'content_version' => $scene->content_version + 1,
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
            'affected_chapter_ids' => array_values(array_unique($affectedChapterIds)),
        ]);
    }

    /**
     * Convert scene HTML to plain text without gluing words together across
     * block boundaries — a naive strip_tags turns `<p>to</p><p>ward</p>`
     * into "toward" and produces phantom matches. Mirrors the editor, where
     * text in different paragraphs can never form a single word.
     */
    private function htmlToSearchableText(string $html): string
    {
        $html = preg_replace('#<br\s*/?>#i', ' ', $html) ?? $html;
        $html = preg_replace('#</(?:p|h[1-6]|li|blockquote|div)>#i', '$0 ', $html) ?? $html;

        return $this->decodeHtmlText(strip_tags($html));
    }

    private function normalizeQuery(string $query): string
    {
        return Str::of(Str::replace("\u{00A0}", ' ', $query))->trim()->toString();
    }

    private function decodeHtmlText(string $text): string
    {
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return Str::replace("\u{00A0}", ' ', $decoded);
    }

    /**
     * Build a regex pattern from search options. Returns null if the regex is invalid.
     */
    private function buildPattern(string $query, bool $caseSensitive, bool $wholeWord, bool $useRegex): ?string
    {
        $flags = ($caseSensitive ? '' : 'i').'u';

        if ($useRegex) {
            $pattern = '~'.$this->escapeRegexDelimiter($query, '~').'~'.$flags;

            return @preg_match($pattern, '') !== false ? $pattern : null;
        }

        $escaped = preg_quote($query, '~');

        if ($wholeWord) {
            return '~\b'.$escaped.'\b~'.$flags;
        }

        return '~'.$escaped.'~'.$flags;
    }

    private function escapeRegexDelimiter(string $query, string $delimiter): string
    {
        $escaped = '';
        $precedingBackslashes = 0;

        for ($index = 0, $length = strlen($query); $index < $length; $index++) {
            $character = $query[$index];

            if ($character === $delimiter && $precedingBackslashes % 2 === 0) {
                $escaped .= '\\';
            }

            $escaped .= $character;
            $precedingBackslashes = $character === '\\' ? $precedingBackslashes + 1 : 0;
        }

        return $escaped;
    }

    /**
     * Find all match positions in plain text.
     *
     * @return array<int, array{start: int, length: int}>
     */
    private function findMatches(string $text, string $pattern): array
    {
        $matchCount = preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);

        if ($matchCount === false || $matchCount === 0) {
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
    private function replaceInHtml(string $html, string $pattern, string $replace, bool $expandCaptures): array
    {
        $totalCount = 0;

        $content = $this->transformHtmlTextNodes($html, function (string $textNode) use (
            $pattern,
            $replace,
            $expandCaptures,
            &$totalCount,
        ): string {
            if ($textNode === '') {
                return $textNode;
            }

            $textContent = $this->decodeHtmlText($textNode);
            if ($expandCaptures) {
                $replaced = @preg_replace($pattern, $replace, $textContent, -1, $count);
            } else {
                $replaced = @preg_replace_callback($pattern, fn (): string => $replace, $textContent, -1, $count);
            }

            if ($replaced === null || $count === 0) {
                return $textNode;
            }

            $totalCount += $count;

            return htmlspecialchars(
                $replaced,
                ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_HTML5,
                'UTF-8',
            );
        });

        return [
            'content' => $content,
            'count' => $totalCount,
        ];
    }

    /**
     * Apply a callback only to text outside HTML tags. Tag boundaries are
     * quote-aware so a `>` inside an attribute value cannot expose the
     * remaining attribute text to replacement.
     *
     * @param  callable(string): string  $transform
     */
    private function transformHtmlTextNodes(string $html, callable $transform): string
    {
        $result = '';
        $textStart = 0;
        $length = strlen($html);
        $index = 0;

        while ($index < $length) {
            if ($html[$index] !== '<') {
                $index++;

                continue;
            }

            $result .= $transform(substr($html, $textStart, $index - $textStart));
            $tagStart = $index;

            if (substr($html, $index, 4) === '<!--') {
                $commentEnd = strpos($html, '-->', $index + 4);
                if ($commentEnd === false) {
                    return $result.substr($html, $tagStart);
                }

                $index = $commentEnd + 3;
                $result .= substr($html, $tagStart, $index - $tagStart);
                $textStart = $index;

                continue;
            }

            $quote = null;
            $index++;

            while ($index < $length) {
                $character = $html[$index];

                if ($quote !== null) {
                    if ($character === $quote) {
                        $quote = null;
                    }
                } elseif ($character === '"' || $character === "'") {
                    $quote = $character;
                } elseif ($character === '>') {
                    break;
                }

                $index++;
            }

            if ($index >= $length) {
                return $result.substr($html, $tagStart);
            }

            $index++;
            $result .= substr($html, $tagStart, $index - $tagStart);
            $textStart = $index;
        }

        return $result.$transform(substr($html, $textStart));
    }

    private function invalidRegexResponse(): JsonResponse
    {
        return response()->json([
            'message' => __('The regular expression is invalid.'),
            'code' => 'invalid_regex',
        ], 422);
    }
}
