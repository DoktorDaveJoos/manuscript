<?php

namespace App\Http\Controllers;

use App\Ai\Agents\EditorialChatAgent;
use App\Jobs\RunEditorialReviewJob;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\EditorialReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return Inertia::render('books/editorial-review', [
            'book' => $book->only('id', 'title'),
            'reviews' => $reviews,
        ]);
    }

    public function store(Book $book): JsonResponse
    {
        $this->ensureAiConfigured();

        $inProgress = $book->editorialReviews()
            ->whereIn('status', ['pending', 'analyzing', 'synthesizing'])
            ->exists();

        if ($inProgress) {
            return response()->json([
                'message' => __('An editorial review is already in progress for this book.'),
            ], 409);
        }

        $review = $book->editorialReviews()->create([
            'status' => 'pending',
        ]);

        RunEditorialReviewJob::dispatch($review);

        return response()->json([
            'message' => __('Editorial review started.'),
            'review' => $review,
        ], 201);
    }

    public function show(Book $book, EditorialReview $review): Response
    {
        abort_if($review->book_id !== $book->id, 404);

        $review->load(['sections', 'chapterNotes']);

        $chapters = $book->chapters()
            ->orderBy('reader_order')
            ->get(['id', 'title', 'reader_order']);

        return Inertia::render('books/editorial-review-show', [
            'book' => $book->only('id', 'title'),
            'review' => $review,
            'chapters' => $chapters,
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

    public function chat(Request $request, Book $book, EditorialReview $review): StreamableAgentResponse
    {
        $this->ensureAiConfigured();

        abort_if($review->book_id !== $book->id, 404);

        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'history' => ['nullable', 'array', 'max:50'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:10000'],
            'section_type' => ['nullable', 'string'],
            'finding_index' => ['nullable', 'integer'],
        ]);

        $agent = new EditorialChatAgent(
            $book,
            $review,
            $request->input('history', []),
            $request->input('section_type'),
            $request->input('finding_index'),
        );

        return $agent->stream($request->input('message'));
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
