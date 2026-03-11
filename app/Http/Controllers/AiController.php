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
use App\Services\Normalization\NormalizationService;
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
            'scene_purpose' => $chapter->scene_purpose,
            'value_shift' => $chapter->value_shift,
            'emotional_state_open' => $chapter->emotional_state_open,
            'emotional_state_close' => $chapter->emotional_state_close,
            'emotional_shift_magnitude' => $chapter->emotional_shift_magnitude,
            'micro_tension_score' => $chapter->micro_tension_score,
            'pacing_feel' => $chapter->pacing_feel,
            'entry_hook_score' => $chapter->entry_hook_score,
            'exit_hook_score' => $chapter->exit_hook_score,
            'sensory_grounding' => $chapter->sensory_grounding,
            'information_delivery' => $chapter->information_delivery,
            'analyses' => $analyses,
        ]);
    }

    public function chat(Request $request, Book $book): StreamableAgentResponse
    {
        $this->ensureAiConfigured();

        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'chapter_id' => ['nullable', 'integer'],
            'history' => ['nullable', 'array', 'max:50'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:10000'],
        ]);

        $chapter = $request->input('chapter_id')
            ? $book->chapters()->findOrFail($request->input('chapter_id'))
            : null;

        $agent = new BookChatAgent($book, $chapter, $request->input('history', []));

        return $agent->stream($request->input('message'));
    }

    public function analyze(RunAnalysisRequest $request, Book $book): JsonResponse
    {
        $type = AnalysisType::from($request->validated('type'));
        $chapterId = $request->validated('chapter_id');
        $chapter = $chapterId ? Chapter::query()->findOrFail($chapterId) : null;

        RunAnalysisJob::dispatch($book, $type, $chapter);

        return response()->json(['message' => __('Analysis started.')]);
    }

    public function extractCharacters(Book $book, Chapter $chapter): JsonResponse
    {
        ExtractEntitiesJob::dispatch($book, $chapter);

        return response()->json(['message' => __('Entity extraction started.')]);
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

        return response()->json(['message' => __(':count embedding jobs dispatched.', ['count' => $dispatched])]);
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
        );
    }

    public function beautify(Book $book, Chapter $chapter): StreamableAgentResponse
    {
        return $this->streamAgentRevision(
            $book,
            $chapter,
            new TextBeautifier($book),
            __('Restructure the following chapter text:'),
            VersionSource::Beautify,
            __('AI text beautification'),
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
        $content = $chapter->getContentWithSceneBreaks();
        if (blank($content)) {
            $content = $currentVersion?->content;
        }
        abort_if(blank($content), 422, __('Chapter has no content to process.'));

        $wordCount = str_word_count(strip_tags($content));
        abort_if($wordCount > 12000, 422, __('Chapter is too long for AI revision (:count words). Consider splitting it into smaller chapters.', ['count' => $wordCount]));

        // Sync currentVersion content from scenes so the diff baseline is accurate
        if ($currentVersion) {
            $currentVersion->update(['content' => $chapter->getFullContent()]);
        }

        $sceneMap = $chapter->scenes->map(fn ($s) => [
            'title' => $s->title,
            'sort_order' => $s->sort_order,
        ])->values()->toArray();

        return $agent->stream(
            "{$promptPrefix}\n\n{$content}",
        )->then(function ($response) use ($book, $chapter, $currentVersion, $source, $changeSummary, $sceneMap) {
            $nextNumber = ($currentVersion?->version_number ?? 0) + 1;

            $normalized = app(NormalizationService::class)->normalize(
                html_entity_decode($response->text, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                $book->language,
            );

            $chapter->versions()->create([
                'version_number' => $nextNumber,
                'content' => $normalized['content'],
                'source' => $source,
                'change_summary' => $changeSummary,
                'is_current' => false,
                'status' => VersionStatus::Pending,
                'scene_map' => $sceneMap,
            ]);
        });
    }

    public function resetUsage(Book $book): JsonResponse
    {
        $book->resetAiUsage();

        return response()->json(['message' => __('AI usage counters reset.')]);
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
