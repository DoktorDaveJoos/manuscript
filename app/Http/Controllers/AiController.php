<?php

namespace App\Http\Controllers;

use App\Ai\Agents\BookChatAgent;
use App\Ai\Agents\NextChapterAdvisor;
use App\Ai\Agents\ProseReviser;
use App\Ai\Agents\TextBeautifier;
use App\Enums\AnalysisType;
use App\Enums\VersionSource;
use App\Enums\VersionStatus;
use App\Http\Requests\RunAnalysisRequest;
use App\Jobs\AnalyzeChapterJob;
use App\Jobs\ExtractEntitiesJob;
use App\Jobs\GenerateEmbeddingsJob;
use App\Jobs\RunAnalysisJob;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Chapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Responses\StreamableAgentResponse;

class AiController extends Controller
{
    public function analyzeChapter(Book $book, Chapter $chapter): JsonResponse
    {
        $chapter->update([
            'analysis_status' => 'pending',
            'analysis_error' => null,
        ]);

        AnalyzeChapterJob::dispatch($book, $chapter);

        return response()->json(['status' => 'pending']);
    }

    public function chapterAnalysisStatus(Book $book, Chapter $chapter): JsonResponse
    {
        $analyses = $chapter->analyses()
            ->get()
            ->keyBy(fn ($a) => $a->type->value);

        return response()->json([
            'analysis_status' => $chapter->analysis_status,
            'analysis_error' => $chapter->analysis_error,
            'analyzed_at' => $chapter->analyzed_at?->toISOString(),
            'tension_score' => $chapter->tension_score,
            'hook_score' => $chapter->hook_score,
            'hook_type' => $chapter->hook_type,
            'summary' => $chapter->summary,
            'analyses' => $analyses,
        ]);
    }

    public function chat(Request $request, Book $book): StreamableAgentResponse
    {
        $this->ensureAiConfigured();

        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $agent = new BookChatAgent($book);

        return $agent->stream($request->input('message'));
    }

    public function analyze(RunAnalysisRequest $request, Book $book): JsonResponse
    {
        $type = AnalysisType::from($request->validated('type'));
        $chapterId = $request->validated('chapter_id');
        $chapter = $chapterId ? Chapter::query()->findOrFail($chapterId) : null;

        RunAnalysisJob::dispatch($book, $type, $chapter);

        return response()->json(['message' => 'Analysis started.']);
    }

    public function extractCharacters(Book $book, Chapter $chapter): JsonResponse
    {
        ExtractEntitiesJob::dispatch($book, $chapter);

        return response()->json(['message' => 'Entity extraction started.']);
    }

    public function nextChapter(Book $book): JsonResponse
    {
        $this->ensureAiConfigured();

        $agent = new NextChapterAdvisor($book);
        $response = $agent->prompt(
            "Based on the current state of '{$book->title}', suggest what should happen in the next chapter.",
        );

        return response()->json($response->toArray());
    }

    public function embed(Book $book): JsonResponse
    {
        $chapters = $book->chapters()->with('currentVersion')->get();
        $dispatched = 0;

        foreach ($chapters as $chapter) {
            if ($chapter->currentVersion) {
                GenerateEmbeddingsJob::dispatch($book, $chapter->currentVersion);
                $dispatched++;
            }
        }

        return response()->json(['message' => "{$dispatched} embedding jobs dispatched."]);
    }

    public function revise(Book $book, Chapter $chapter): StreamableAgentResponse
    {
        return $this->streamAgentRevision(
            $book,
            $chapter,
            new ProseReviser($book),
            'Revise the following chapter text:',
            VersionSource::AiRevision,
            'AI prose revision',
        );
    }

    public function beautify(Book $book, Chapter $chapter): StreamableAgentResponse
    {
        return $this->streamAgentRevision(
            $book,
            $chapter,
            new TextBeautifier($book),
            'Restructure the following chapter text:',
            VersionSource::Beautify,
            'AI text beautification',
        );
    }

    private function streamAgentRevision(
        Book $book,
        Chapter $chapter,
        Agent $agent,
        string $promptPrefix,
        VersionSource $source,
        string $changeSummary,
    ): StreamableAgentResponse {
        $this->ensureAiConfigured();

        $chapter->loadMissing(['currentVersion', 'scenes']);
        $currentVersion = $chapter->currentVersion;
        $content = $chapter->getFullContent();
        if (blank($content)) {
            $content = $currentVersion?->content;
        }
        abort_if(blank($content), 422, 'Chapter has no content to process.');

        return $agent->stream(
            "{$promptPrefix}\n\n{$content}",
        )->then(function ($response) use ($chapter, $currentVersion, $source, $changeSummary) {
            $nextNumber = ($currentVersion?->version_number ?? 0) + 1;

            $chapter->versions()->create([
                'version_number' => $nextNumber,
                'content' => $response->text,
                'source' => $source,
                'change_summary' => $changeSummary,
                'is_current' => false,
                'status' => VersionStatus::Pending,
            ]);
        });
    }

    public function resetUsage(Book $book): JsonResponse
    {
        $book->resetAiUsage();

        return response()->json(['message' => 'AI usage counters reset.']);
    }

    private function ensureAiConfigured(): void
    {
        set_time_limit(300);

        $setting = AiSetting::activeProvider();

        abort_if(
            ! $setting || ! $setting->isConfigured(),
            422,
            'No AI provider configured.',
        );

        $setting->injectConfig();
    }
}
