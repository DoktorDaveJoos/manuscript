<?php

namespace App\Http\Controllers;

use App\Enums\VersionSource;
use App\Models\Book;
use App\Models\Chapter;
use App\Services\Normalization\NormalizationService;
use App\Support\WordCount;
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
            $preview = $this->previewForChapter($chapter, $book->language);

            if ($preview['total_changes'] > 0) {
                $results[] = [
                    'id' => $chapter->id,
                    'title' => $chapter->title,
                    'changes' => $preview['changes'],
                    'total_changes' => $preview['total_changes'],
                ];
                $totalChanges += $preview['total_changes'];
            }
        }

        return response()->json([
            'chapters' => $results,
            'total_changes' => $totalChanges,
        ]);
    }

    public function applyBook(Book $book): JsonResponse
    {
        $chapters = $book->chapters()->with(['currentVersion', 'scenes'])->get();
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
        abort_unless($chapter->book_id === $book->id, 404);

        $chapter->load(['currentVersion', 'scenes']);

        $preview = $this->previewForChapter($chapter, $book->language);

        $chapters = [];
        if ($preview['total_changes'] > 0) {
            $chapters[] = [
                'id' => $chapter->id,
                'title' => $chapter->title,
                'changes' => $preview['changes'],
                'total_changes' => $preview['total_changes'],
            ];
        }

        return response()->json([
            'chapters' => $chapters,
            'total_changes' => $preview['total_changes'],
        ]);
    }

    public function applyChapter(Book $book, Chapter $chapter): JsonResponse
    {
        abort_unless($chapter->book_id === $book->id, 404);

        $chapter->load(['currentVersion', 'scenes']);

        $applied = DB::transaction(function () use ($chapter, $book) {
            return $this->applyToChapter($chapter, $book->language);
        });

        return response()->json([
            'applied' => $applied,
        ]);
    }

    /**
     * @return array{changes: array<int, array{rule: string, count: int}>, total_changes: int}
     */
    private function previewForChapter(Chapter $chapter, string $language): array
    {
        $scenes = $chapter->scenes->sortBy('sort_order')->values();

        if ($scenes->isEmpty()) {
            $content = $chapter->currentVersion?->content;
            if (! $content) {
                return ['changes' => [], 'total_changes' => 0];
            }

            $result = $this->normalization->normalize($content, $language);

            return [
                'changes' => $result['changes'],
                'total_changes' => $result['total_changes'],
            ];
        }

        $changesByRule = [];
        $totalChanges = 0;

        foreach ($scenes as $scene) {
            if (! $scene->content) {
                continue;
            }

            $result = $this->normalization->normalize($scene->content, $language);
            $totalChanges += $result['total_changes'];

            foreach ($result['changes'] as $change) {
                $changesByRule[$change['rule']] = ($changesByRule[$change['rule']] ?? 0) + $change['count'];
            }
        }

        $changes = [];
        foreach ($changesByRule as $rule => $count) {
            $changes[] = ['rule' => $rule, 'count' => $count];
        }

        return [
            'changes' => $changes,
            'total_changes' => $totalChanges,
        ];
    }

    private function applyToChapter(Chapter $chapter, string $language): bool
    {
        $chapter->loadMissing(['scenes', 'currentVersion']);
        $scenes = $chapter->scenes->sortBy('sort_order')->values();

        if ($scenes->isEmpty()) {
            $content = $chapter->currentVersion?->content;
            if (! $content) {
                return false;
            }

            $result = $this->normalization->normalize($content, $language);
            if ($result['total_changes'] === 0) {
                return false;
            }

            $chapter->versions()->where('is_current', true)->update(['is_current' => false]);
            $latestVersion = $chapter->versions()->max('version_number') ?? 0;

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

        $normalizedBySceneId = [];
        $changesByRule = [];
        $totalChanges = 0;

        foreach ($scenes as $scene) {
            if (! $scene->content) {
                continue;
            }

            $result = $this->normalization->normalize($scene->content, $language);
            if ($result['total_changes'] === 0) {
                continue;
            }

            $normalizedBySceneId[$scene->id] = $result['content'];
            $totalChanges += $result['total_changes'];

            foreach ($result['changes'] as $change) {
                $changesByRule[$change['rule']] = ($changesByRule[$change['rule']] ?? 0) + $change['count'];
            }
        }

        if ($totalChanges === 0) {
            return false;
        }

        // Persist the outgoing multi-scene snapshot before overwriting scenes.
        $chapter->syncCurrentVersionContent();

        foreach ($scenes as $scene) {
            if (! array_key_exists($scene->id, $normalizedBySceneId)) {
                continue;
            }

            $newContent = $normalizedBySceneId[$scene->id];
            $scene->update([
                'content' => $newContent,
                'word_count' => WordCount::count($newContent),
                'content_version' => $scene->content_version + 1,
            ]);
        }

        $chapter->load('scenes');

        $changes = [];
        foreach ($changesByRule as $rule => $count) {
            $changes[] = ['rule' => $rule, 'count' => $count];
        }

        // Mark current version as not current
        $chapter->versions()->where('is_current', true)->update(['is_current' => false]);

        $latestVersion = $chapter->versions()->max('version_number') ?? 0;
        $sceneMap = $chapter->captureSceneMap();

        // Create new version with normalized multi-scene content
        $chapter->versions()->create([
            'version_number' => $latestVersion + 1,
            'content' => $chapter->getContentWithSceneBreaks(),
            'source' => VersionSource::Normalization,
            'change_summary' => $this->buildChangeSummary($changes),
            'is_current' => true,
            'scene_map' => $sceneMap !== [] ? $sceneMap : null,
        ]);

        $chapter->recalculateWordCount();

        return true;
    }

    /**
     * @param  array<int, array{rule: string, count: int}>  $changes
     */
    private function buildChangeSummary(array $changes): string
    {
        $parts = array_map(fn ($c) => "{$c['rule']}: {$c['count']}", $changes);

        return __('Normalization').' — '.implode(', ', $parts);
    }
}
