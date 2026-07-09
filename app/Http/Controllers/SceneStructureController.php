<?php

namespace App\Http\Controllers;

use App\Ai\Agents\SceneStructurer;
use App\Enums\VersionSource;
use App\Enums\VersionStatus;
use App\Http\Controllers\Concerns\EnsuresAiConfigured;
use App\Http\Requests\SceneStructureApplyRequest;
use App\Models\Book;
use App\Models\Chapter;
use App\Support\HtmlBlocks;
use App\Support\WordCount;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SceneStructureController extends Controller
{
    use EnsuresAiConfigured;

    private const EXCERPT_LENGTH = 140;

    /**
     * Ask the AI for a scene structure proposal. The agent only returns
     * boundary positions and titles; the prose itself is never sent back by
     * the model, so the chapter text cannot drift. The proposal is stateless:
     * the client posts the boundaries back to `apply` together with the
     * content hash captured here.
     */
    public function suggest(Book $book, Chapter $chapter): JsonResponse
    {
        abort_unless($chapter->book_id === $book->id, 404);

        $this->ensureAiConfigured();

        [$content, $blocks] = $this->chapterBlocks($chapter);

        $wordCount = WordCount::count($content);
        abort_if($wordCount > 12000, 422, __('Chapter is too long for AI scene structuring (:count words). Consider splitting it into smaller chapters.', ['count' => $wordCount]));

        $numbered = collect($blocks)
            ->map(fn (string $block, int $index) => "[{$index}] ".Str::squish(strip_tags($block)))
            ->implode("\n");

        $response = (new SceneStructurer($book, $chapter))->prompt(
            __('Propose a scene structure for the following chapter paragraphs:')."\n\n".$numbered,
        );

        $boundaries = $this->normalizeBoundaries($response['scenes'] ?? [], count($blocks));

        return response()->json([
            'scenes' => $this->describeScenes($boundaries, $blocks),
            'paragraph_count' => count($blocks),
            'current_scene_count' => $chapter->scenes->count(),
            'content_hash' => hash('xxh128', $content),
        ]);
    }

    public function apply(SceneStructureApplyRequest $request, Book $book, Chapter $chapter): JsonResponse
    {
        abort_unless($chapter->book_id === $book->id, 404);

        [$content, $blocks] = $this->chapterBlocks($chapter);

        abort_if(
            hash('xxh128', $content) !== $request->contentHash(),
            409,
            __('This chapter has changed since the structure was suggested — run it again.'),
        );

        $scenes = $request->scenes();
        $starts = array_column($scenes, 'start_paragraph');

        abort_if($starts[0] !== 0, 422, __('The first scene must start at the beginning of the chapter.'));

        foreach ($starts as $index => $start) {
            abort_if($start >= count($blocks), 422, __('Scene boundaries no longer match the chapter.'));
            abort_if($index > 0 && $start <= $starts[$index - 1], 422, __('Scene boundaries must be strictly increasing.'));
        }

        $newContent = collect($starts)
            ->map(fn (int $start, int $index) => implode('', array_slice(
                $blocks,
                $start,
                ($starts[$index + 1] ?? count($blocks)) - $start,
            )))
            ->implode('<hr>');

        $sceneMap = collect($scenes)
            ->map(fn (array $scene, int $index) => ['title' => $scene['title'], 'sort_order' => $index])
            ->values()
            ->all();

        DB::transaction(function () use ($chapter, $newContent, $sceneMap) {
            $previous = $chapter->currentVersion;

            $chapter->versions()->where('is_current', true)->update(['is_current' => false]);

            $chapter->versions()->create([
                'version_number' => ($previous?->version_number ?? 0) + 1,
                'content' => $newContent,
                'source' => VersionSource::SceneStructure,
                'change_summary' => __('AI scene structure'),
                'is_current' => true,
                'status' => VersionStatus::Accepted,
                'scene_map' => $sceneMap,
            ]);

            $chapter->replaceSceneContents($newContent, $sceneMap);
        });

        return response()->json([
            'message' => __('Scene structure applied.'),
            'scene_count' => count($sceneMap),
        ]);
    }

    /**
     * The chapter's live content (synced into the current version, existing
     * scene breaks ignored) and its top-level blocks.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    private function chapterBlocks(Chapter $chapter): array
    {
        $chapter->loadMissing(['currentVersion', 'scenes']);
        $chapter->syncCurrentVersionContent();

        $content = $chapter->getFullContent();
        $blocks = HtmlBlocks::split($content);

        abort_if($blocks === [], 422, __('Chapter has no content to structure.'));

        return [$content, $blocks];
    }

    /**
     * The model occasionally returns unsorted, duplicate, or out-of-range
     * boundaries; repair what is repairable instead of failing the run.
     *
     * @param  array<int, mixed>  $scenes
     * @return array<int, array{title: string, start_paragraph: int}>
     */
    private function normalizeBoundaries(array $scenes, int $blockCount): array
    {
        $boundaries = collect($scenes)
            ->filter(fn ($scene) => is_array($scene) && isset($scene['start_paragraph']))
            ->map(fn (array $scene) => [
                'title' => trim((string) ($scene['title'] ?? '')) ?: __('Scene'),
                'start_paragraph' => (int) $scene['start_paragraph'],
            ])
            ->filter(fn (array $scene) => $scene['start_paragraph'] >= 0 && $scene['start_paragraph'] < $blockCount)
            ->unique('start_paragraph')
            ->sortBy('start_paragraph')
            ->values();

        if ($boundaries->isEmpty()) {
            return [['title' => __('Scene'), 'start_paragraph' => 0]];
        }

        return $boundaries
            ->map(fn (array $scene, int $index) => $index === 0
                ? [...$scene, 'start_paragraph' => 0]
                : $scene)
            ->all();
    }

    /**
     * @param  array<int, array{title: string, start_paragraph: int}>  $boundaries
     * @param  array<int, string>  $blocks
     * @return array<int, array{title: string, start_paragraph: int, word_count: int, excerpt: string}>
     */
    private function describeScenes(array $boundaries, array $blocks): array
    {
        $starts = array_column($boundaries, 'start_paragraph');

        return collect($boundaries)
            ->map(function (array $scene, int $index) use ($starts, $blocks) {
                $segment = implode('', array_slice(
                    $blocks,
                    $starts[$index],
                    ($starts[$index + 1] ?? count($blocks)) - $starts[$index],
                ));

                return [
                    ...$scene,
                    'word_count' => WordCount::count($segment),
                    'excerpt' => Str::limit(Str::squish(strip_tags($segment)), self::EXCERPT_LENGTH),
                ];
            })
            ->all();
    }
}
