<?php

namespace App\Jobs;

use App\Ai\Agents\ChapterAnalyzer;
use App\Ai\Agents\CharacterExtractor;
use App\Models\AiPreparation;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\HealthSnapshot;
use App\Services\ChunkingService;
use App\Services\EmbeddingService;
use App\Services\StoryBibleService;
use App\Services\WritingStyleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Throwable;

class PrepareBookForAi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    /** @var list<string> */
    private array $completedPhases = [];

    /** @var list<array{phase: string, chapter: string|null, error: string}> */
    private array $phaseErrors = [];

    public function __construct(
        private Book $book,
        private AiPreparation $preparation
    ) {}

    public function handle(
        ChunkingService $chunking,
        EmbeddingService $embedding,
        WritingStyleService $styleService,
        StoryBibleService $storyBibleService,
    ): void {
        $setting = AiSetting::forProvider($this->book->ai_provider);

        if (! $setting->isConfigured()) {
            $this->markFailed('No API key configured for '.$this->book->ai_provider->label());

            return;
        }

        $chapters = $this->book->chapters()
            ->with('currentVersion')
            ->orderBy('reader_order')
            ->get();

        $this->preparation->update([
            'total_chapters' => $chapters->count(),
            'status' => 'running',
        ]);

        // Phase 1: Chunk all chapters
        $allChunks = collect();
        $sampleTexts = [];
        $this->startPhase('chunking', $chapters->count());

        foreach ($chapters as $chapter) {
            $content = $chapter->currentVersion?->content;
            if (! $content) {
                $this->advancePhase();

                continue;
            }

            $chapter->currentVersion->chunks()->each(fn ($chunk) => $chunk->deleteEmbedding());
            $chunks = $chunking->chunkVersion($chapter->currentVersion);
            $allChunks = $allChunks->merge($chunks);

            if (count($sampleTexts) < 3) {
                $sampleTexts[] = strip_tags($content);
            }

            $this->preparation->increment('processed_chapters');
            $this->advancePhase();
        }

        $this->completePhase('chunking');

        // Phase 2: Generate embeddings
        if ($this->book->ai_provider->supportsEmbeddings() && $allChunks->isNotEmpty()) {
            $this->startPhase('embedding', 1);

            try {
                $embedding->embedChunks($allChunks, $this->book);
                $this->preparation->update(['embedded_chunks' => $allChunks->count()]);
            } catch (Throwable $e) {
                $this->markFailed('Embedding failed: '.$e->getMessage());

                return;
            }

            $this->advancePhase();
            $this->completePhase('embedding');
        }

        // Phase 3: Extract writing style
        $this->startPhase('writing_style', 1);

        if (! empty($sampleTexts)) {
            $combinedSample = implode("\n\n---\n\n", $sampleTexts);
            $words = preg_split('/\s+/', $combinedSample);
            if (count($words) > 5000) {
                $combinedSample = implode(' ', array_slice($words, 0, 5000));
            }

            try {
                $style = $styleService->extract($combinedSample, $this->book);
                $this->book->update(['writing_style' => $style]);
            } catch (Throwable $e) {
                $this->logPhaseError('writing_style', null, $e->getMessage());
            }
        }

        $this->advancePhase();
        $this->completePhase('writing_style');

        // Phase 4: Chapter analysis (summary + hook + tension + plot points)
        $this->runChapterAnalysis($chapters);

        // Phase 5: Character extraction
        $this->runCharacterExtraction($chapters);

        // Phase 6: Build Story Bible
        $this->startPhase('story_bible', 1);

        try {
            $storyBibleService->build($this->book);
        } catch (Throwable $e) {
            $this->logPhaseError('story_bible', null, $e->getMessage());
        }

        $this->advancePhase();
        $this->completePhase('story_bible');

        // Phase 7: Health analysis (computed from chapter data, no AI call needed)
        $this->startPhase('health_analysis', 1);
        $this->upsertHealthSnapshot($chapters);
        $this->advancePhase();
        $this->completePhase('health_analysis');

        $this->preparation->update([
            'status' => 'completed',
            'completed_phases' => $this->completedPhases,
            'phase_errors' => $this->phaseErrors ?: null,
        ]);
    }

    /**
     * Analyze each chapter for summary, hooks, tension, and plot points.
     *
     * @param  Collection<int, \App\Models\Chapter>  $chapters
     */
    private function runChapterAnalysis(Collection $chapters): void
    {
        $chaptersWithContent = $chapters->filter(fn ($ch) => $ch->currentVersion?->content);
        $this->startPhase('chapter_analysis', $chaptersWithContent->count());

        $rollingContext = '';

        foreach ($chapters as $chapter) {
            $content = $chapter->currentVersion?->content;
            if (! $content) {
                continue;
            }

            $plainText = strip_tags($content);
            $words = preg_split('/\s+/', $plainText);
            $capped = count($words) > 3000 ? implode(' ', array_slice($words, 0, 3000)) : $plainText;

            try {
                $agent = new ChapterAnalyzer($this->book, $rollingContext);
                $response = $agent->prompt("Analyze this chapter:\n\nTitle: {$chapter->title}\n\n{$capped}");

                $chapter->update([
                    'summary' => $response['summary'] ?? null,
                    'tension_score' => $response['tension_score'] ?? null,
                    'hook_score' => $response['hook_score'] ?? null,
                    'hook_type' => $response['hook_type'] ?? null,
                ]);

                // Store plot points
                $plotPoints = $response['plot_points'] ?? [];
                foreach ($plotPoints as $point) {
                    if (! is_array($point) || empty($point['description'])) {
                        continue;
                    }

                    $this->book->plotPoints()->create([
                        'title' => $point['title'] ?? $point['description'],
                        'description' => $point['description'],
                        'type' => $point['type'] ?? 'worldbuilding',
                        'status' => 'fulfilled',
                        'actual_chapter_id' => $chapter->id,
                        'sort_order' => $chapter->reader_order,
                        'is_ai_derived' => true,
                    ]);
                }

                // Build rolling context from summaries (capped at ~750 words)
                $summary = $response['summary'] ?? '';
                if ($summary) {
                    $rollingContext .= "Ch{$chapter->reader_order} ({$chapter->title}): {$summary}\n";
                    $contextWords = preg_split('/\s+/', $rollingContext);
                    if (count($contextWords) > 750) {
                        $rollingContext = implode(' ', array_slice($contextWords, -750));
                    }
                }
            } catch (Throwable $e) {
                $this->logPhaseError('chapter_analysis', $chapter->title, $e->getMessage());
            }

            $this->advancePhase();
        }

        $this->completePhase('chapter_analysis');
    }

    /**
     * Extract characters from each chapter.
     *
     * @param  Collection<int, \App\Models\Chapter>  $chapters
     */
    private function runCharacterExtraction(Collection $chapters): void
    {
        $chaptersWithContent = $chapters->filter(fn ($ch) => $ch->currentVersion?->content);
        $this->startPhase('character_extraction', $chaptersWithContent->count());

        foreach ($chapters as $chapter) {
            $content = $chapter->currentVersion?->content;
            if (! $content) {
                continue;
            }

            try {
                $agent = new CharacterExtractor($this->book);
                $response = $agent->prompt("Extract all characters from the following chapter text:\n\n{$content}");

                $characters = $response['characters'] ?? [];

                foreach ($characters as $characterData) {
                    if (! is_array($characterData) || empty($characterData['name'])) {
                        continue;
                    }

                    $this->book->characters()->updateOrCreate(
                        ['name' => $characterData['name']],
                        [
                            'aliases' => $characterData['aliases'] ?? null,
                            'description' => $characterData['description'] ?? null,
                            'is_ai_extracted' => true,
                            'first_appearance' => $chapter->id,
                        ],
                    );
                }
            } catch (Throwable $e) {
                $this->logPhaseError('character_extraction', $chapter->title, $e->getMessage());
            }

            $this->advancePhase();
        }

        $this->completePhase('character_extraction');
    }

    /**
     * Upsert a health snapshot from current chapter analysis data.
     *
     * @param  Collection<int, \App\Models\Chapter>  $chapters
     */
    private function upsertHealthSnapshot(Collection $chapters): void
    {
        $analyzed = $chapters->filter(fn ($ch) => $ch->hook_score !== null);

        if ($analyzed->isEmpty()) {
            return;
        }

        $avgHook = $analyzed->avg('hook_score');
        $hookScore = min(100, max(0, (int) round($avgHook * 10)));

        $wordCounts = $chapters->pluck('word_count')->filter(fn ($w) => $w > 0);
        if ($wordCounts->count() > 1) {
            $mean = $wordCounts->avg();
            $variance = $wordCounts->map(fn ($w) => pow($w - $mean, 2))->avg();
            $cv = $mean > 0 ? sqrt($variance) / $mean : 0;
            $pacingScore = min(100, max(0, (int) round(100 - abs($cv - 0.25) * 200)));
        } else {
            $pacingScore = 50;
        }

        $tensionChapters = $analyzed->filter(fn ($ch) => $ch->tension_score !== null);
        $tensionScore = $tensionChapters->count() > 2
            ? min(100, max(0, (int) round($tensionChapters->avg('tension_score') * 10)))
            : 50;

        $storylineCounts = $chapters->groupBy('storyline_id')->map->count();
        if ($storylineCounts->count() > 1) {
            $weaveScore = min(100, max(0, (int) round(($storylineCounts->min() / $storylineCounts->max()) * 100)));
        } else {
            $weaveScore = 75;
        }

        $compositeScore = (int) round(
            $hookScore * 0.35 + $pacingScore * 0.25 + $tensionScore * 0.25 + $weaveScore * 0.15
        );

        HealthSnapshot::query()->upsert(
            [
                'book_id' => $this->book->id,
                'recorded_at' => now()->toDateString(),
                'composite_score' => $compositeScore,
                'hooks_score' => $hookScore,
                'pacing_score' => $pacingScore,
                'tension_score' => $tensionScore,
                'weave_score' => $weaveScore,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ['book_id', 'recorded_at'],
            ['composite_score', 'hooks_score', 'pacing_score', 'tension_score', 'weave_score', 'updated_at'],
        );
    }

    private function startPhase(string $phase, int $total): void
    {
        $this->preparation->update([
            'current_phase' => $phase,
            'current_phase_total' => $total,
            'current_phase_progress' => 0,
        ]);
    }

    private function advancePhase(): void
    {
        $this->preparation->increment('current_phase_progress');
    }

    private function completePhase(string $phase): void
    {
        $this->completedPhases[] = $phase;
        $this->preparation->update([
            'completed_phases' => $this->completedPhases,
        ]);
    }

    private function logPhaseError(string $phase, ?string $chapter, string $error): void
    {
        $this->phaseErrors[] = [
            'phase' => $phase,
            'chapter' => $chapter,
            'error' => $error,
        ];

        $this->preparation->update([
            'phase_errors' => $this->phaseErrors,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->markFailed($exception?->getMessage() ?? 'Unknown error');
    }

    private function markFailed(string $message): void
    {
        $this->preparation->update([
            'status' => 'failed',
            'error_message' => $message,
            'completed_phases' => $this->completedPhases,
            'phase_errors' => $this->phaseErrors ?: null,
        ]);
    }
}
