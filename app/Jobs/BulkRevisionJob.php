<?php

namespace App\Jobs;

use App\Enums\BulkRevisionType;
use App\Enums\VersionStatus;
use App\Models\AiSetting;
use App\Models\Book;
use App\Services\Normalization\NormalizationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BulkRevisionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(
        private Book $book,
        private BulkRevisionType $type,
    ) {}

    public function handle(): void
    {
        $setting = AiSetting::activeProvider();

        if (! $setting || ! $setting->isConfigured()) {
            $this->updateStatus('failed', error: 'No AI provider configured.');

            return;
        }

        $setting->injectConfig();
        set_time_limit(3600);

        $chapters = $this->book->chapters()
            ->with(['currentVersion', 'scenes'])
            ->orderBy('reader_order')
            ->get();

        $processable = $chapters->filter(function ($chapter) {
            $content = $chapter->getContentWithSceneBreaks() ?: $chapter->currentVersion?->content;

            return filled($content) && str_word_count(strip_tags($content)) <= 12000;
        });

        $this->updateStatus('running', total: $processable->count());

        $processed = 0;

        foreach ($processable as $chapter) {
            try {
                $this->processChapter($chapter);
                $processed++;
                $this->updateStatus('running', total: $processable->count(), processed: $processed);
            } catch (\Throwable $e) {
                Log::warning("Bulk {$this->type->value} failed for chapter {$chapter->id}: {$e->getMessage()}");
            }
        }

        $this->updateStatus('completed', total: $processable->count(), processed: $processed);
    }

    private function processChapter($chapter): void
    {
        $content = $chapter->getContentWithSceneBreaks() ?: $chapter->currentVersion?->content;

        $agent = $this->type->agent($this->book, $chapter);

        $response = $agent->prompt("{$this->type->promptPrefix()}\n\n{$content}");

        $chapter->syncCurrentVersionContent();
        $currentVersion = $chapter->currentVersion;

        $sceneMap = $chapter->scenes->map(fn ($s) => [
            'title' => $s->title,
            'sort_order' => $s->sort_order,
        ])->values()->toArray();

        $nextNumber = ($currentVersion?->version_number ?? 0) + 1;

        $normalized = app(NormalizationService::class)->normalize(
            html_entity_decode($response->text, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $this->book->language,
        );

        $chapter->versions()->create([
            'version_number' => $nextNumber,
            'content' => $normalized['content'],
            'source' => $this->type->versionSource(),
            'change_summary' => $this->type->changeSummary(),
            'is_current' => false,
            'status' => VersionStatus::Pending,
            'scene_map' => $sceneMap,
        ]);
    }

    public static function cacheKey(int $bookId): string
    {
        return "bulk_revision:{$bookId}";
    }

    private function updateStatus(string $status, ?string $error = null, int $total = 0, int $processed = 0): void
    {
        Cache::put(self::cacheKey($this->book->id), [
            'type' => $this->type->value,
            'status' => $status,
            'total' => $total,
            'processed' => $processed,
            'error' => $error,
        ], now()->addHours(1));
    }

    public function failed(?\Throwable $exception): void
    {
        $this->updateStatus('failed', error: $exception?->getMessage() ?? 'Unknown error');
    }
}
