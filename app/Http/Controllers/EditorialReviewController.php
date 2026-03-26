<?php

namespace App\Http\Controllers;

use App\Ai\Agents\EditorialChatAgent;
use App\Enums\EditorialSectionType;
use App\Jobs\RunEditorialReviewJob;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\EditorialReview;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Ai\Responses\StreamableAgentResponse;

class EditorialReviewController extends Controller
{
    public function index(Book $book): Response
    {
        $reviews = $book->editorialReviews()
            ->latest()
            ->get();

        $latestReview = $reviews
            ->where('status', 'completed')
            ->first();

        $latestReview?->load(['sections', 'chapterNotes']);
        $latestReview?->sections->each->ensureFindingKeys();

        return Inertia::render('books/editorial-review', [
            'book' => $book->only('id', 'title', 'author', 'language'),
            'reviews' => $reviews,
            'latestReview' => $latestReview,
            'chapters' => $this->chapterList($book),
        ]);
    }

    public function store(Book $book): JsonResponse
    {
        $this->ensureAiConfigured();

        abort_if(
            $book->editorialReviews()
                ->whereNotIn('status', ['completed', 'failed'])
                ->exists(),
            422,
            __('An editorial review is already in progress for this book.'),
        );

        $review = $book->editorialReviews()->create([
            'status' => 'pending',
            'started_at' => now(),
        ]);

        RunEditorialReviewJob::dispatch($book, $review);

        return response()->json([
            'message' => __('Editorial review started.'),
            'review' => $review,
        ]);
    }

    public function show(Book $book, EditorialReview $review): Response
    {
        abort_if($review->book_id !== $book->id, 404);

        $review->load(['sections', 'chapterNotes']);
        $review->sections->each->ensureFindingKeys();

        return Inertia::render('books/editorial-review', [
            'book' => $book->only('id', 'title', 'author', 'language'),
            'review' => $review,
            'chapters' => $this->chapterList($book),
            'reviews' => $book->editorialReviews()->latest()->get(),
        ]);
    }

    public function progress(Book $book, EditorialReview $review): JsonResponse
    {
        abort_if($review->book_id !== $book->id, 404);

        return response()->json([
            'status' => $review->status,
            'progress' => $review->progress,
            'error_message' => $review->error_message,
        ]);
    }

    public function toggleFinding(Request $request, Book $book, EditorialReview $review): JsonResponse
    {
        abort_if($review->book_id !== $book->id, 404);

        $request->validate([
            'key' => ['required', 'string', 'max:32'],
        ]);

        $key = $request->input('key');
        $resolved = $review->resolved_findings ?? [];

        if (in_array($key, $resolved, true)) {
            $resolved = array_values(array_filter($resolved, fn ($k) => $k !== $key));
        } else {
            $resolved[] = $key;
        }

        $review->update(['resolved_findings' => $resolved]);

        return response()->json(['resolved_findings' => $resolved]);
    }

    public function chat(Request $request, Book $book, EditorialReview $review): StreamableAgentResponse
    {
        $this->ensureAiConfigured();

        abort_if($review->book_id !== $book->id, 404);

        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'history' => ['nullable', 'array', 'max:50'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:10000'],
            'section_type' => ['nullable', 'string', Rule::enum(EditorialSectionType::class)],
            'finding_index' => ['nullable', 'integer', 'min:0'],
        ]);

        $context = $this->buildEditorialContext($review, $request);

        $agent = new EditorialChatAgent(
            $book,
            $context,
            $request->input('history', []),
        );

        return $agent->stream($request->input('message'));
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
        set_time_limit(300);

        $setting = AiSetting::activeProvider();

        abort_if(
            ! $setting || ! $setting->isConfigured(),
            422,
            __('No AI provider configured.'),
        );

        $setting->injectConfig();
    }
}
