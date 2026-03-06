<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportBookRequest;
use App\Http\Requests\UpdateBookAiModelRequest;
use App\Models\AiSetting;
use App\Models\Book;
use App\Services\ExportService;
use App\Services\WritingStyleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BookSettingsController extends Controller
{
    public function index(Book $book): Response
    {
        $book->load('storylines');

        return Inertia::render('settings/book/index', [
            'book' => $book->only('id', 'title', 'ai_provider', 'ai_model', 'ai_enabled', 'writing_style_text'),
            'enabled_providers' => $this->enabledProvidersPayload(),
            'writing_style_display' => $book->writing_style_display,
            'rules' => $book->prose_pass_rules ?? Book::defaultProsePassRules(),
            'storylines' => $book->storylines->map(fn ($s) => $s->only('id', 'name')),
        ]);
    }

    public function aiModel(Book $book): Response
    {
        $enabledProviders = $this->enabledProvidersPayload();

        return Inertia::render('settings/book/ai-model', [
            'book' => $book->only('id', 'title', 'ai_provider', 'ai_model', 'ai_enabled'),
            'enabled_providers' => $enabledProviders,
        ]);
    }

    public function updateAiModel(UpdateBookAiModelRequest $request, Book $book): JsonResponse
    {
        $book->update($request->validated());

        return response()->json(['message' => 'AI model settings updated.']);
    }

    public function writingStyle(Book $book): Response
    {
        return Inertia::render('settings/book/writing-style', [
            'book' => $book->only('id', 'title', 'writing_style_text', 'writing_style'),
            'writing_style_display' => $book->writing_style_display,
        ]);
    }

    public function updateWritingStyle(Request $request, Book $book): JsonResponse
    {
        $request->validate([
            'writing_style_text' => ['required', 'string', 'max:5000'],
        ]);

        $book->update([
            'writing_style_text' => $request->input('writing_style_text'),
        ]);

        return response()->json(['message' => 'Writing style updated.']);
    }

    public function regenerateWritingStyle(Book $book, WritingStyleService $service): JsonResponse
    {
        $chapters = $book->chapters()
            ->whereHas('versions', fn ($q) => $q->where('is_current', true))
            ->with(['versions' => fn ($q) => $q->where('is_current', true)])
            ->limit(5)
            ->get();

        $sampleText = $chapters
            ->map(fn ($ch) => $ch->versions->first()?->content ?? '')
            ->filter()
            ->implode("\n\n");

        if (blank($sampleText)) {
            return response()->json(['message' => 'No chapter content available for style analysis.'], 422);
        }

        $result = $service->extract($sampleText, $book);

        $proseText = Book::formatWritingStyle($result);

        $book->update([
            'writing_style' => $result,
            'writing_style_text' => $proseText,
        ]);

        return response()->json([
            'message' => 'Writing style regenerated.',
            'writing_style_text' => $proseText,
        ]);
    }

    public function prosePassRules(Book $book): Response
    {
        return Inertia::render('settings/book/prose-pass-rules', [
            'book' => $book->only('id', 'title'),
            'rules' => $book->prose_pass_rules ?? Book::defaultProsePassRules(),
        ]);
    }

    public function updateProsePassRules(Request $request, Book $book): JsonResponse
    {
        $request->validate([
            'rules' => ['required', 'array'],
            'rules.*.key' => ['required', 'string'],
            'rules.*.label' => ['required', 'string'],
            'rules.*.description' => ['required', 'string'],
            'rules.*.enabled' => ['required', 'boolean'],
        ]);

        $book->update([
            'prose_pass_rules' => $request->input('rules'),
        ]);

        return response()->json(['message' => 'Prose pass rules updated.']);
    }

    public function export(Book $book): Response
    {
        $book->load('storylines');

        return Inertia::render('settings/book/export', [
            'book' => $book->only('id', 'title'),
            'storylines' => $book->storylines->map(fn ($s) => $s->only('id', 'name')),
        ]);
    }

    public function doExport(ExportBookRequest $request, Book $book, ExportService $service): BinaryFileResponse
    {
        return $service->export($book, $request->validated());
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{provider: string, label: string, text_model: string|null}>
     */
    private function enabledProvidersPayload(): \Illuminate\Support\Collection
    {
        return AiSetting::enabledProviders()->map(fn (AiSetting $s) => [
            'provider' => $s->provider->value,
            'label' => $s->provider->label(),
            'text_model' => $s->text_model,
        ]);
    }
}
