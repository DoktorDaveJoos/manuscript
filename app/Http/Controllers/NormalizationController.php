<?php

namespace App\Http\Controllers;

use App\Enums\VersionSource;
use App\Models\Book;
use App\Models\Chapter;
use App\Services\Normalization\NormalizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class NormalizationController extends Controller
{
    public function __construct(
        private NormalizationService $normalization
    ) {}

    public function previewBook(Book $book): JsonResponse
    {
        $chapters = $book->chapters()->with(['currentVersion', 'scenes'])->get();

        $results = [];
        $totalChanges = 0;

        foreach ($chapters as $chapter) {
            $content = $chapter->getFullContent();
            if (! $content) {
                continue;
            }

            $result = $this->normalization->normalize($content, $book->language);

            if ($result['total_changes'] > 0) {
                $results[] = [
                    'id' => $chapter->id,
                    'title' => $chapter->title,
                    'changes' => $result['changes'],
                    'total_changes' => $result['total_changes'],
                ];
                $totalChanges += $result['total_changes'];
            }
        }

        return response()->json([
            'chapters' => $results,
            'total_changes' => $totalChanges,
        ]);
    }

    public function applyBook(Book $book): JsonResponse
    {
        $chapters = $book->chapters()->with('currentVersion')->get();
        $applied = 0;

        DB::transaction(function () use ($chapters, $book, &$applied) {
            foreach ($chapters as $chapter) {
                if ($this->applyToChapter($chapter, $book->language)) {
                    $applied++;
                }
            }
        });

        return response()->json([
            'applied_chapters' => $applied,
        ]);
    }

    public function previewChapter(Book $book, Chapter $chapter): JsonResponse
    {
        $chapter->load('currentVersion');

        $content = $chapter->currentVersion?->content;
        if (! $content) {
            return response()->json([
                'chapters' => [],
                'total_changes' => 0,
            ]);
        }

        $result = $this->normalization->normalize($content, $book->language);

        $chapters = [];
        if ($result['total_changes'] > 0) {
            $chapters[] = [
                'id' => $chapter->id,
                'title' => $chapter->title,
                'changes' => $result['changes'],
                'total_changes' => $result['total_changes'],
            ];
        }

        return response()->json([
            'chapters' => $chapters,
            'total_changes' => $result['total_changes'],
        ]);
    }

    public function applyChapter(Book $book, Chapter $chapter): JsonResponse
    {
        $chapter->load('currentVersion');

        $applied = DB::transaction(function () use ($chapter, $book) {
            return $this->applyToChapter($chapter, $book->language);
        });

        return response()->json([
            'applied' => $applied,
        ]);
    }

    private function applyToChapter(Chapter $chapter, string $language): bool
    {
        $chapter->loadMissing('scenes');
        $content = $chapter->getFullContent();
        if (! $content) {
            return false;
        }

        $result = $this->normalization->normalize($content, $language);

        if ($result['total_changes'] === 0) {
            return false;
        }

        // Mark current version as not current
        $chapter->versions()->where('is_current', true)->update(['is_current' => false]);

        $latestVersion = $chapter->versions()->max('version_number') ?? 0;

        // Create new version with normalized content
        $chapter->versions()->create([
            'version_number' => $latestVersion + 1,
            'content' => $result['content'],
            'source' => VersionSource::Normalization,
            'change_summary' => $this->buildChangeSummary($result['changes']),
            'is_current' => true,
        ]);

        $chapter->replaceScenesWithContent($result['content']);

        return true;
    }

    /**
     * @param  array<int, array{rule: string, count: int}>  $changes
     */
    private function buildChangeSummary(array $changes): string
    {
        $parts = array_map(fn ($c) => "{$c['rule']}: {$c['count']}", $changes);

        return 'Normalization — '.implode(', ', $parts);
    }
}
