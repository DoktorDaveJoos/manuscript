<?php

namespace App\Http\Controllers;

use App\Ai\Agents\BookChatAgent;
use App\Ai\Agents\ProseReviser;
use App\Enums\VersionSource;
use App\Enums\VersionStatus;
use App\Http\Controllers\Concerns\EnsuresAiConfigured;
use App\Http\Controllers\Concerns\EnsuresChapterVersion;
use App\Http\Controllers\Concerns\StreamsConversation;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Scene;
use App\Services\Normalization\NormalizationService;
use App\Support\WordCount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiController extends Controller
{
    use EnsuresAiConfigured, EnsuresChapterVersion, StreamsConversation;

    public function chat(Request $request, Book $book): JsonResponse|StreamedResponse
    {
        return $this->streamChat(function () use ($request, $book) {
            $this->ensureAiConfigured();

            $request->validate([
                'message' => ['required', 'string', 'max:2000'],
                'chapter_id' => ['nullable', 'integer'],
                'conversation_id' => ['nullable', 'string', 'max:36'],
            ]);

            $chapter = $request->input('chapter_id')
                ? $book->chapters()->findOrFail($request->input('chapter_id'))
                : null;

            $conversationId = $this->resolveConversation($request);

            $agent = new BookChatAgent($book, $chapter);
            $agent->continue($conversationId, $request->user() ?? (object) ['id' => 0]);

            return $this->streamWithConversationId(
                $agent->stream($request->input('message')),
                $conversationId,
            );
        });
    }

    public function revise(Book $book, Chapter $chapter): StreamableAgentResponse
    {
        return $this->streamAgentRevision(
            $book,
            $chapter,
            new ProseReviser($book, $chapter),
            __('Revise the following chapter text:'),
            VersionSource::AiRevision,
            __('AI prose revision'),
            expectedVersionId: $this->validatedExpectedVersionId(),
        );
    }

    public function reviseScene(Book $book, Chapter $chapter, Scene $scene): StreamableAgentResponse
    {
        abort_unless($chapter->book_id === $book->id, 404);
        abort_unless($scene->chapter_id === $chapter->id, 404);

        return $this->streamAgentRevision(
            $book,
            $chapter,
            new ProseReviser($book, $chapter),
            __('Revise the following scene text:'),
            VersionSource::AiRevision,
            __('AI scene prose revision'),
            $scene,
            expectedVersionId: $this->validatedExpectedVersionId(),
        );
    }

    public function reviseWithEditorialFeedback(Book $book, Chapter $chapter): StreamableAgentResponse
    {
        abort_unless($chapter->book_id === $book->id, 404);

        $directive = $this->editorialDirectiveFor($book, $chapter);

        abort_if($directive === null, 422, __('No completed editorial review has feedback for this chapter. Run an editorial review first.'));

        return $this->streamAgentRevision(
            $book,
            $chapter,
            new ProseReviser($book, $chapter, $directive),
            __('Revise the following chapter text, addressing the editorial feedback from your instructions:'),
            VersionSource::EditorialRewrite,
            __('AI rewrite from editorial feedback'),
            expectedVersionId: $this->validatedExpectedVersionId(),
        );
    }

    /**
     * The latest completed editorial review's feedback for one chapter: the
     * chapter note plus every unresolved finding referencing the chapter.
     * Null when no completed review exists or none of it concerns the chapter.
     */
    private function editorialDirectiveFor(Book $book, Chapter $chapter): ?string
    {
        $review = $book->editorialReviews()
            ->where('status', 'completed')
            ->latest('completed_at')
            ->first();

        if (! $review) {
            return null;
        }

        $note = $review->chapterNoteFor($chapter->id);

        $findings = collect($review->unresolvedFindingsForChapter($chapter->id))
            ->map(fn (array $finding) => sprintf(
                '- [%s] (%s) %s — Recommendation: %s',
                $finding['severity'],
                $finding['section'],
                $finding['description'],
                $finding['recommendation'],
            ))
            ->all();

        $parts = [];

        if ($note) {
            $parts[] = "Chapter note from the editor:\n{$note}";
        }

        if ($findings !== []) {
            $parts[] = "Findings to address:\n".implode("\n", $findings);
        }

        return $parts === [] ? null : implode("\n\n", $parts);
    }

    private function validatedExpectedVersionId(): ?int
    {
        $validated = request()->validate([
            'expected_current_version_id' => ['nullable', 'integer'],
        ]);

        return isset($validated['expected_current_version_id'])
            ? (int) $validated['expected_current_version_id']
            : null;
    }

    private function streamAgentRevision(
        Book $book,
        Chapter $chapter,
        Agent $agent,
        string $promptPrefix,
        VersionSource $source,
        string $changeSummary,
        ?Scene $scene = null,
        ?int $expectedVersionId = null,
    ): StreamableAgentResponse {
        $this->ensureAiConfigured();

        $chapter->loadMissing(['currentVersion', 'scenes']);
        $this->ensureCurrentVersion($chapter, $expectedVersionId);
        $currentVersion = $chapter->currentVersion;

        if ($scene) {
            $content = $scene->content;
            abort_if(blank($content), 422, __('Scene has no content to process.'));
        } else {
            $content = $chapter->getContentWithSceneBreaks();
            if (blank($content)) {
                $content = $currentVersion?->content;
            }
            abort_if(blank($content), 422, __('Chapter has no content to process.'));
        }

        $wordCount = WordCount::count($content);
        abort_if($wordCount > 12000, 422, __('Chapter is too long for AI revision (:count words). Consider splitting it into smaller chapters.', ['count' => $wordCount]));

        $chapter->syncCurrentVersionContent();

        $sceneMap = $chapter->scenes->map(fn ($s) => [
            'title' => $s->title,
            'sort_order' => $s->sort_order,
        ])->values()->toArray();

        return $agent->stream(
            "{$promptPrefix}\n\n{$content}",
        )->then(function ($response) use ($book, $chapter, $currentVersion, $source, $changeSummary, $sceneMap, $scene) {
            $nextNumber = ($currentVersion?->version_number ?? 0) + 1;

            $normalized = app(NormalizationService::class)->normalize(
                html_entity_decode($response->text, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                $book->language,
            );

            // Auto-apply: the revised version becomes the new current version
            // immediately. The previous current version stays in history so the
            // user can still see what changed via "Compare with previous".
            DB::transaction(function () use ($chapter, $currentVersion, $nextNumber, $normalized, $source, $changeSummary, $sceneMap, $scene) {
                $chapter->load('currentVersion');
                $this->ensureCurrentVersion($chapter, $currentVersion?->id);

                if ($scene) {
                    $scene->update(['content' => $normalized['content']]);
                    $chapter->load('scenes');
                    $fullContent = $chapter->getContentWithSceneBreaks();
                } else {
                    $fullContent = $normalized['content'];
                }

                $chapter->versions()
                    ->where('is_current', true)
                    ->update(['is_current' => false]);

                $chapter->versions()->create([
                    'version_number' => $nextNumber,
                    'content' => $fullContent,
                    'source' => $source,
                    'change_summary' => $changeSummary,
                    'is_current' => true,
                    'status' => VersionStatus::Accepted,
                    'scene_map' => $sceneMap,
                ]);

                if (! $scene) {
                    $chapter->replaceSceneContents($normalized['content'], $sceneMap);
                }
            });
        });
    }

    public function resetUsage(Book $book): JsonResponse
    {
        $book->resetAiUsage();

        return response()->json(['message' => __('AI usage counters reset.')]);
    }
}
