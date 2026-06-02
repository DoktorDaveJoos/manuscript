<?php

namespace App\Http\Controllers;

use App\Ai\Agents\RewriteSelectionAgent;
use App\Ai\Support\AiErrorClassifier;
use App\Enums\VersionSource;
use App\Enums\VersionStatus;
use App\Http\Controllers\Concerns\EnsuresAiConfigured;
use App\Http\Controllers\Concerns\EnsuresChapterVersion;
use App\Http\Requests\RewriteSelectionRequest;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Streaming\Events\TextDelta;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RewriteSelectionController extends Controller
{
    use EnsuresAiConfigured;
    use EnsuresChapterVersion;

    public function stream(RewriteSelectionRequest $request, Book $book, Chapter $chapter): StreamedResponse
    {
        abort_unless($chapter->book_id === $book->id, 404);

        $this->ensureAiConfigured();

        $chapter->loadMissing('currentVersion');
        $this->ensureCurrentVersion($chapter, $request->expectedCurrentVersionId());

        // The "before" reference for the diff overlay must reflect what's on
        // screen right now, not the last accepted version.
        $chapter->syncCurrentVersionContent();

        $agent = new RewriteSelectionAgent(
            book: $book,
            chapter: $chapter,
            selection: $request->selection(),
            hint: $request->hint(),
            beforeProse: $request->beforeProse(),
            afterProse: $request->afterProse(),
        );

        $streamable = $agent->stream('Rewrite the SELECTION so it replaces the original passage and reads seamlessly with the surrounding prose.');

        return response()->stream(function () use ($streamable) {
            try {
                foreach ($streamable as $event) {
                    if (! $event instanceof TextDelta || $event->delta === '') {
                        continue;
                    }
                    echo 'data: '.json_encode(['delta' => $event->delta])."\n\n";
                    $this->sseFlush();
                }
            } catch (\Throwable $e) {
                report($e);
                $classified = AiErrorClassifier::classify(
                    $e,
                    AiSetting::activeProvider()?->provider->value,
                );
                echo 'data: '.json_encode([
                    'error' => $classified['message'] ?: __('Rewrite failed.'),
                    'kind' => $classified['kind'],
                    'provider' => $classified['provider'],
                ])."\n\n";
                $this->sseFlush();
            }

            echo "data: [DONE]\n\n";
            $this->sseFlush();
        }, headers: [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function commit(Book $book, Chapter $chapter): JsonResponse
    {
        abort_unless($chapter->book_id === $book->id, 404);

        $validated = request()->validate([
            'expected_current_version_id' => ['nullable', 'integer'],
        ]);
        $expectedId = isset($validated['expected_current_version_id'])
            ? (int) $validated['expected_current_version_id']
            : null;

        return DB::transaction(function () use ($chapter, $expectedId) {
            $chapter->loadMissing(['scenes', 'currentVersion']);
            $this->ensureCurrentVersion($chapter, $expectedId);
            $previous = $chapter->currentVersion;

            $content = $chapter->getContentWithSceneBreaks();

            $sceneMap = $chapter->scenes->map(fn ($scene) => [
                'title' => $scene->title,
                'sort_order' => $scene->sort_order,
            ])->values()->toArray();

            $chapter->versions()->where('is_current', true)->update(['is_current' => false]);

            $new = $chapter->versions()->create([
                'version_number' => ($previous?->version_number ?? 0) + 1,
                'content' => $content,
                'source' => VersionSource::RewriteSelection,
                'is_current' => true,
                'status' => VersionStatus::Accepted,
                'scene_map' => $sceneMap,
            ]);

            return response()->json([
                'previous' => $previous ? $this->serializeVersion($previous) : null,
                'new' => $this->serializeVersion($new),
            ]);
        });
    }

    /**
     * @return array{id: int, version_number: int, content: ?string, source: string, status: string}
     */
    private function serializeVersion(ChapterVersion $version): array
    {
        return [
            'id' => $version->id,
            'version_number' => $version->version_number,
            'content' => $version->content,
            'source' => $version->source->value,
            'status' => $version->status->value,
        ];
    }

    private function sseFlush(): void
    {
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
}
