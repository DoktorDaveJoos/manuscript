<?php

namespace App\Jobs\Preparation;

use App\Models\AiPreparation;
use App\Models\Book;
use App\Services\StoryBibleService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class BuildStoryBible implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(
        private Book $book,
        private AiPreparation $preparation,
    ) {}

    public function handle(StoryBibleService $storyBibleService): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        try {
            $storyBibleService->build($this->book);
            $this->preparation->increment('current_phase_progress');
        } catch (Throwable $e) {
            $this->preparation->appendPhaseError('story_bible', null, $e->getMessage());
        }
    }
}
