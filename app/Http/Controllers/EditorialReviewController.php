<?php

namespace App\Http\Controllers;

use App\Ai\Agents\EditorialChatAgent;
use App\Enums\EditorialReviewErrorCode;
use App\Enums\EditorialSectionType;
use App\Http\Controllers\Concerns\StreamsConversation;
use App\Jobs\RunEditorialReviewJob;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\EditorialReview;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class EditorialReviewController extends Controller
{
    use StreamsConversation;

    public function index(Book $book): Response
    {
        EditorialReview::failStale($book);

        $reviews = $book->editorialReviews()
            ->latest('id')
            ->limit(20)
            ->get();

        $latestReview = $reviews->first(fn ($r) => in_array($r->status, ['pending', 'analyzing', 'synthesizing']))
            ?? $reviews->first(fn ($r) => in_array($r->status, ['failed', 'completed']));

        if ($latestReview && $latestReview->status === 'completed') {
            $latestReview->load(['sections', 'chapterNotes']);
            $latestReview->sections->each->ensureFindingKeys();
        }

        return Inertia::render('books/editorial-review', [
            'book' => $book->only('id', 'title', 'author', 'language'),
            'reviews' => $reviews,
            'latestReview' => $latestReview,
            'chapters' => $this->chapterList($book),
            'editedChaptersCount' => $this->chaptersEditedSinceLastReview($book),
        ]);
    }

    public function store(Book $book): JsonResponse
    {
        if ($configurationError = $this->configureAiForRequest()) {
            return $configurationError;
        }

        try {
            $result = DB::transaction(function () use ($book): array {
                Book::query()->whereKey($book->id)->lockForUpdate()->firstOrFail();

                // A run whose worker died would otherwise block new reviews until
                // the hourly cleanup; fail this book's stale runs first.
                EditorialReview::failStale($book);

                $activeReview = $this->activeReview($book);
                if ($activeReview) {
                    return ['created' => false, 'review' => $activeReview];
                }

                return [
                    'created' => true,
                    'review' => $book->editorialReviews()->create([
                        'status' => 'pending',
                        'started_at' => now(),
                        'progress' => ['phase' => 'pending'],
                    ]),
                ];
            });
        } catch (QueryException $exception) {
            report($exception);

            return $this->applicationUnavailableResponse(
                __('The app could not save the new editorial review. No review was started; try again in a moment.'),
            );
        }

        if (! $result['created']) {
            return $this->reviewAlreadyRunningResponse($result['review']);
        }

        return $this->dispatchReview(
            $book,
            $result['review'],
            __('Editorial review started.'),
        );
    }

    /**
     * Resume a failed review on the same record: the pipeline skips chapter
     * notes and sections that are still valid, while invalidating synthesis
     * if chapter content changed after the failure.
     */
    public function resume(Book $book, EditorialReview $review): JsonResponse
    {
        abort_if($review->book_id !== $book->id, 404);

        if ($configurationError = $this->configureAiForRequest()) {
            return $configurationError;
        }

        try {
            $result = DB::transaction(function () use ($book, $review): array {
                Book::query()->whereKey($book->id)->lockForUpdate()->firstOrFail();
                EditorialReview::failStale($book);

                $lockedReview = EditorialReview::query()
                    ->whereKey($review->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $activeReview = $this->activeReview($book);
                if ($activeReview) {
                    return ['resumed' => false, 'review' => $activeReview];
                }

                abort_if($lockedReview->status !== 'failed', 422, __('Only a failed review can be resumed.'));

                $invalidateSynthesis = $this->synthesisIsStale($book, $lockedReview);

                if ($invalidateSynthesis) {
                    $lockedReview->sections()->delete();
                }

                $lockedReview->update([
                    'status' => 'pending',
                    'progress' => ['phase' => 'pending'],
                    'error_message' => null,
                    'error_code' => null,
                    'batch_id' => null,
                    'completed_at' => null,
                    ...($invalidateSynthesis ? [
                        'overall_score' => null,
                        'executive_summary' => null,
                        'top_strengths' => null,
                        'top_improvements' => null,
                        'is_pre_editorial' => false,
                        'resolved_findings' => [],
                    ] : []),
                ]);

                return ['resumed' => true, 'review' => $lockedReview];
            });
        } catch (QueryException $exception) {
            report($exception);

            return $this->applicationUnavailableResponse(
                __('The app could not save the resumed editorial review. Your previous failed state is unchanged; try again in a moment.'),
                $review,
            );
        }

        if (! $result['resumed']) {
            return $this->reviewAlreadyRunningResponse($result['review']);
        }

        return $this->dispatchReview(
            $book,
            $result['review'],
            __('Editorial review resumed.'),
        );
    }

    public function show(Book $book, EditorialReview $review): Response
    {
        abort_if($review->book_id !== $book->id, 404);

        EditorialReview::failStale($book);
        $review->refresh();
        $review->load(['sections', 'chapterNotes']);
        $review->sections->each->ensureFindingKeys();

        return Inertia::render('books/editorial-review', [
            'book' => $book->only('id', 'title', 'author', 'language'),
            'latestReview' => $review,
            'chapters' => $this->chapterList($book),
            'reviews' => $book->editorialReviews()->latest('id')->limit(20)->get(),
            'editedChaptersCount' => $this->chaptersEditedSinceLastReview($book),
        ]);
    }

    public function progress(Book $book, EditorialReview $review): JsonResponse
    {
        abort_if($review->book_id !== $book->id, 404);

        EditorialReview::failStale($book);
        $review->refresh();

        return response()->json([
            'status' => $review->status,
            'progress' => $review->progress,
            'error_message' => $review->error_message,
            'error_code' => $review->error_code,
        ]);
    }

    public function toggleFinding(Request $request, Book $book, EditorialReview $review): JsonResponse
    {
        abort_if($review->book_id !== $book->id, 404);

        $request->validate([
            'key' => ['required', 'string', 'max:64'],
        ]);

        $key = $request->input('key');

        $resolved = DB::transaction(function () use ($review, $key) {
            $review = EditorialReview::lockForUpdate()->find($review->id);
            $resolved = $review->resolved_findings ?? [];

            if (in_array($key, $resolved, true)) {
                $resolved = array_values(array_filter($resolved, fn ($k) => $k !== $key));
            } else {
                $resolved[] = $key;
            }

            $review->update(['resolved_findings' => $resolved]);

            return $resolved;
        });

        return response()->json(['resolved_findings' => $resolved]);
    }

    public function chat(Request $request, Book $book, EditorialReview $review): JsonResponse|StreamedResponse
    {
        return $this->streamChat(function () use ($request, $book, $review) {
            set_time_limit(300);

            $this->ensureAiConfigured();

            abort_if($review->book_id !== $book->id, 404);
            abort_if($review->status !== 'completed', 422, __('Editorial review is not yet completed.'));

            $request->validate([
                'message' => ['required', 'string', 'max:2000'],
                'conversation_id' => ['nullable', 'string', 'max:36'],
                'section_type' => ['nullable', 'string', Rule::enum(EditorialSectionType::class)],
                'finding_index' => ['nullable', 'integer', 'min:0'],
            ]);

            $conversationId = $this->resolveConversation($request);
            $context = $this->buildEditorialContext($review, $request);

            $agent = new EditorialChatAgent($book, $context);
            $agent->continue($conversationId, $request->user() ?? (object) ['id' => 0]);

            return $this->streamWithConversationId(
                $agent->stream($request->input('message')),
                $conversationId,
            );
        });
    }

    /**
     * Chapters whose scenes changed after the newest completed review
     * finished — the signal for whether another review round is worthwhile.
     * Null when the book has no completed review to compare against.
     */
    private function chaptersEditedSinceLastReview(Book $book): ?int
    {
        $lastCompletedAt = $book->editorialReviews()
            ->where('status', 'completed')
            ->max('completed_at');

        if (! $lastCompletedAt) {
            return null;
        }

        return $book->chapters()
            ->whereHas('scenes', fn ($query) => $query->where('updated_at', '>', $lastCompletedAt))
            ->count();
    }

    /**
     * @return Collection<int, Chapter>
     */
    private function chapterList(Book $book): Collection
    {
        return $book->chapters()
            ->orderBy('reader_order')
            ->get(['id', 'title', 'reader_order']);
    }

    private function buildEditorialContext(EditorialReview $review, Request $request): string
    {
        $parts = [];

        if ($review->executive_summary) {
            $parts[] = "Executive Summary:\n{$review->executive_summary}";
        }

        $sectionType = $request->input('section_type');
        $findingIndex = $request->input('finding_index');

        if ($sectionType) {
            $section = $review->sections()
                ->where('type', $sectionType)
                ->first();

            if ($section) {
                $parts[] = "Section: {$section->type->value}\nScore: {$section->score}/100\nSummary: {$section->summary}";

                if ($findingIndex !== null && isset($section->findings[$findingIndex])) {
                    $finding = $section->findings[$findingIndex];
                    $parts[] = "Specific finding being discussed:\n"
                        ."Severity: {$finding['severity']}\n"
                        ."Description: {$finding['description']}\n"
                        .'Recommendation: '.($finding['recommendation'] ?? 'N/A');
                }
            }
        }

        return implode("\n\n", $parts);
    }

    private function ensureAiConfigured(): void
    {
        $setting = AiSetting::activeProvider();

        abort_if(
            ! $setting || ! $setting->isConfigured(),
            422,
            $this->missingProviderMessage(),
        );

        $setting->injectConfig();
    }

    private function configureAiForRequest(): ?JsonResponse
    {
        $setting = AiSetting::activeProvider();

        if (! $setting || ! $setting->isConfigured()) {
            return response()->json([
                'message' => $this->missingProviderMessage(),
                'error_code' => EditorialReviewErrorCode::NoProvider->value,
            ], 422);
        }

        $setting->injectConfig();

        return null;
    }

    private function missingProviderMessage(): string
    {
        return __('Editorial Review needs an active AI provider. Open AI Settings to select a provider and add its credentials, then return here to start or continue.');
    }

    private function activeReview(Book $book): ?EditorialReview
    {
        return $book->editorialReviews()
            ->whereNotIn('status', ['completed', 'failed'])
            ->latest('id')
            ->first();
    }

    private function reviewAlreadyRunningResponse(EditorialReview $review): JsonResponse
    {
        return response()->json([
            'message' => __('An editorial review is already in progress for this book. Showing its current status instead.'),
            'error_code' => 'already_running',
            'review' => $review,
        ], 422);
    }

    private function dispatchReview(Book $book, EditorialReview $review, string $successMessage): JsonResponse
    {
        try {
            RunEditorialReviewJob::dispatch($book, $review);
        } catch (Throwable $exception) {
            report($exception);

            $message = __('The app could not queue the editorial review. Your saved progress is safe; try again in a moment.');

            try {
                $review->update([
                    'status' => 'failed',
                    'error_message' => $message,
                    'error_code' => EditorialReviewErrorCode::QueueUnavailable->value,
                ]);
            } catch (Throwable $persistenceException) {
                report($persistenceException);

                $review->forceFill([
                    'status' => 'failed',
                    'error_message' => $message,
                    'error_code' => EditorialReviewErrorCode::QueueUnavailable->value,
                ]);
            }

            return response()->json([
                'message' => $message,
                'error_code' => EditorialReviewErrorCode::QueueUnavailable->value,
                'review' => $review,
            ], 503);
        }

        return response()->json([
            'message' => $successMessage,
            'review' => $review,
        ]);
    }

    private function applicationUnavailableResponse(string $message, ?EditorialReview $review = null): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'error_code' => EditorialReviewErrorCode::AppUnavailable->value,
            ...($review ? ['review' => $review] : []),
        ], 503);
    }

    /**
     * Existing synthesized sections are only reusable while every current
     * chapter still has a note for its current content hash.
     */
    private function synthesisIsStale(Book $book, EditorialReview $review): bool
    {
        if (! $review->sections()->exists() && ! $review->executive_summary) {
            return false;
        }

        $hasUnhashedContent = $book->chapters()
            ->whereNull('content_hash')
            ->whereHas('scenes', fn ($query) => $query
                ->whereNotNull('content')
                ->where('content', '!=', ''))
            ->exists();

        if ($hasUnhashedContent) {
            return true;
        }

        $currentHashes = $book->chapters()->pluck('content_hash', 'id');
        $noteHashes = $review->chapterNotes()
            ->latest('id')
            ->get(['chapter_id', 'content_hash'])
            ->unique('chapter_id')
            ->pluck('content_hash', 'chapter_id');

        $currentChapterChanged = $currentHashes->contains(
            fn (?string $hash, int|string $chapterId): bool => $hash !== null && $noteHashes->get($chapterId) !== $hash,
        );

        $reviewReferencesDeletedChapter = $noteHashes->keys()->contains(
            fn (int|string $chapterId): bool => ! $currentHashes->has($chapterId),
        );

        return $currentChapterChanged || $reviewReferencesDeletedChapter;
    }
}
