<?php

use App\Database\SqliteVecConnector;
use App\Http\Controllers\ActController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\AiConversationController;
use App\Http\Controllers\AiDashboardController;
use App\Http\Controllers\AiPreparationController;
use App\Http\Controllers\AiSettingsController;
use App\Http\Controllers\AppSettingsController;
use App\Http\Controllers\BeatController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BookSettingsController;
use App\Http\Controllers\CanvasController;
use App\Http\Controllers\ChapterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EditorialReviewController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\NormalizationController;
use App\Http\Controllers\PlotAiController;
use App\Http\Controllers\PlotCoachController;
use App\Http\Controllers\PlotController;
use App\Http\Controllers\PlotPointConnectionController;
use App\Http\Controllers\PlotPointController;
use App\Http\Controllers\PlotSetupController;
use App\Http\Controllers\PublishController;
use App\Http\Controllers\SceneController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StorylineController;
use App\Http\Controllers\TrashController;
use App\Http\Controllers\UpdateController;
use App\Http\Controllers\WikiController;
use App\Http\Controllers\WikiPanelController;
use App\Http\Controllers\WritingGoalController;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Lightweight loading page that the NativePHP window opens first.
// Renders instantly (no DB, no Inertia, no JS bundle) and then redirects
// to '/'. Gives the user immediate visual feedback while Laravel + the
// React bundle warm up on cold start.
Route::get('/loading', fn () => view('loading'))->name('loading');

// Skip Inertia's share() — both endpoints are plain JSON and share() runs
// an AppSetting::getMany that would fire on every loading-screen poll.
Route::withoutMiddleware(HandleInertiaRequests::class)->group(function () {
    Route::get('/ready', function () {
        try {
            DB::select('SELECT 1');
        } catch (Throwable) {
            return response()->json(['ready' => false], 503);
        }

        return response()->json(['ready' => true]);
    })->name('ready');

    Route::get('/repair-status', function () {
        $marker = SqliteVecConnector::markerPath();

        if (! file_exists($marker)) {
            return response()->json(['state' => 'idle']);
        }

        $payload = json_decode((string) @file_get_contents($marker), true) ?: [];

        return response()->json([
            'state' => 'repairing',
            'started_at' => $payload['started_at'] ?? null,
        ]);
    })->name('repair-status');
});

Route::get('/', [BookController::class, 'index'])->name('books.index');
Route::post('/books', [BookController::class, 'store'])->name('books.store');
Route::patch('/books/{book}', [BookController::class, 'update'])->name('books.update');
Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('books.destroy');
Route::post('/books/{book}/duplicate', [BookController::class, 'duplicate'])->name('books.duplicate');
Route::get('/books/{book}/import', [BookController::class, 'import'])->name('books.import');
Route::post('/books/{book}/import/parse', [BookController::class, 'parse'])->name('books.import.parse');
Route::post('/books/{book}/import/confirm', [BookController::class, 'confirmImport'])->name('books.import.confirm');
Route::post('/books/{book}/import/skip', [BookController::class, 'skipImport'])->name('books.import.skip');

Route::get('/books/{book}/dashboard', [DashboardController::class, 'show'])->name('books.dashboard');
Route::put('/books/{book}/writing-goal', [WritingGoalController::class, 'update'])->name('books.writing-goal.update');
Route::patch('/books/{book}/milestone/dismiss', [DashboardController::class, 'dismissMilestone'])->name('books.milestone.dismiss');
Route::get('/books/{book}/wiki', [WikiController::class, 'index'])->name('books.wiki');
Route::post('/books/{book}/characters', [WikiController::class, 'storeCharacter'])->name('characters.store');
Route::patch('/books/{book}/characters/{character}', [WikiController::class, 'updateCharacter'])->name('characters.update');
Route::delete('/books/{book}/characters/{character}', [WikiController::class, 'destroyCharacter'])->name('characters.destroy');
Route::post('/books/{book}/wiki-entries', [WikiController::class, 'storeEntry'])->name('wikiEntries.store');
Route::patch('/books/{book}/wiki-entries/{wikiEntry}', [WikiController::class, 'updateEntry'])->name('wikiEntries.update');
Route::delete('/books/{book}/wiki-entries/{wikiEntry}', [WikiController::class, 'destroyEntry'])->name('wikiEntries.destroy');
Route::get('/books/{book}/wiki/panel', [WikiPanelController::class, 'index'])->name('wiki.panel.index');
Route::post('/books/{book}/wiki/panel/connect', [WikiPanelController::class, 'connect'])->name('wiki.panel.connect');
Route::post('/books/{book}/wiki/panel/disconnect', [WikiPanelController::class, 'disconnect'])->name('wiki.panel.disconnect');
Route::patch('/books/{book}/wiki/panel/characters/{character}', [WikiPanelController::class, 'updateCharacter'])->name('wiki.panel.updateCharacter');
Route::patch('/books/{book}/wiki/panel/characters/{character}/role', [WikiPanelController::class, 'updateRole'])->name('wiki.panel.updateRole');
Route::patch('/books/{book}/wiki/panel/wiki-entries/{wikiEntry}', [WikiPanelController::class, 'updateWikiEntry'])->name('wiki.panel.updateWikiEntry');
// Plot board routes are gated behind Pro (see license middleware group below)

Route::get('/books/{book}/editor', [ChapterController::class, 'editor'])->name('books.editor');
Route::post('/books/{book}/chapters', [ChapterController::class, 'store'])->name('chapters.store');
Route::get('/books/{book}/chapters/{chapter}', [ChapterController::class, 'show'])->name('chapters.show');
Route::get('/books/{book}/chapters/{chapter}/json', [ChapterController::class, 'showJson'])->name('chapters.show.json');
Route::patch('/books/{book}/chapters/{chapter}/title', [ChapterController::class, 'updateTitle'])->name('chapters.updateTitle');
Route::put('/books/{book}/chapters/{chapter}/content', [ChapterController::class, 'updateContent'])->name('chapters.updateContent');
Route::get('/books/{book}/chapters/{chapter}/versions', [ChapterController::class, 'versions'])->name('chapters.versions');
Route::post('/books/{book}/chapters/{chapter}/versions', [ChapterController::class, 'createSnapshot'])->name('chapters.createSnapshot');
Route::post('/books/{book}/chapters/{chapter}/versions/{version}/restore', [ChapterController::class, 'restoreVersion'])->name('chapters.restoreVersion');
Route::delete('/books/{book}/chapters/{chapter}/versions/{version}', [ChapterController::class, 'destroyVersion'])->name('chapters.destroyVersion');
Route::post('/books/{book}/chapters/{chapter}/versions/{version}/accept', [ChapterController::class, 'acceptVersion'])->name('chapters.acceptVersion');
Route::post('/books/{book}/chapters/{chapter}/versions/{version}/accept-partial', [ChapterController::class, 'acceptPartialVersion'])->name('chapters.acceptPartialVersion');
Route::post('/books/{book}/chapters/{chapter}/versions/{version}/reject', [ChapterController::class, 'rejectVersion'])->name('chapters.rejectVersion');
Route::patch('/books/{book}/chapters/{chapter}/notes', [ChapterController::class, 'updateNotes'])->name('chapters.updateNotes');
Route::post('/books/{book}/chapters/{chapter}/split', [ChapterController::class, 'split'])->name('chapters.split');
Route::delete('/books/{book}/chapters/{chapter}', [ChapterController::class, 'destroy'])->name('chapters.destroy');
Route::patch('/books/{book}/chapters/{chapter}/status', [ChapterController::class, 'updateStatus'])->name('chapters.updateStatus');
Route::patch('/books/{book}/chapters/{chapter}/act', [ChapterController::class, 'assignAct'])->name('chapters.assignAct');
Route::post('/books/{book}/chapters/reorder', [ChapterController::class, 'reorder'])->name('chapters.reorder');
Route::post('/books/{book}/chapters/interleave', [ChapterController::class, 'interleave'])->name('chapters.interleave');

Route::post('/books/{book}/chapters/{chapter}/scenes', [SceneController::class, 'store'])->name('scenes.store');
Route::put('/books/{book}/chapters/{chapter}/scenes/{scene}/content', [SceneController::class, 'updateContent'])->name('scenes.updateContent');
Route::patch('/books/{book}/chapters/{chapter}/scenes/{scene}/title', [SceneController::class, 'updateTitle'])->name('scenes.updateTitle');
Route::post('/books/{book}/chapters/{chapter}/scenes/reorder', [SceneController::class, 'reorder'])->name('scenes.reorder');
Route::delete('/books/{book}/chapters/{chapter}/scenes/{scene}', [SceneController::class, 'destroy'])->name('scenes.destroy');

Route::post('/books/{book}/search', [SearchController::class, 'search'])->name('books.search');
Route::post('/books/{book}/search/replace-all', [SearchController::class, 'replaceAll'])->name('books.search.replaceAll');

Route::post('/books/{book}/storylines', [StorylineController::class, 'store'])->name('storylines.store');
Route::patch('/books/{book}/storylines/{storyline}', [StorylineController::class, 'update'])->name('storylines.update');
Route::delete('/books/{book}/storylines/{storyline}', [StorylineController::class, 'destroy'])->name('storylines.destroy');
Route::post('/books/{book}/storylines/reorder', [StorylineController::class, 'reorder'])->name('storylines.reorder');

Route::get('/books/{book}/trash', [TrashController::class, 'index'])->name('books.trash.index');
Route::post('/books/{book}/trash/restore', [TrashController::class, 'restore'])->name('books.trash.restore');
Route::delete('/books/{book}/trash', [TrashController::class, 'empty'])->name('books.trash.empty');

Route::post('/books/{book}/normalize/preview', [NormalizationController::class, 'previewBook'])->name('books.normalize.preview');
Route::post('/books/{book}/normalize/apply', [NormalizationController::class, 'applyBook'])->name('books.normalize.apply');
Route::post('/books/{book}/chapters/{chapter}/normalize/preview', [NormalizationController::class, 'previewChapter'])->name('chapters.normalize.preview');
Route::post('/books/{book}/chapters/{chapter}/normalize/apply', [NormalizationController::class, 'applyChapter'])->name('chapters.normalize.apply');

// Updates
Route::post('/update/check', [UpdateController::class, 'check'])->name('update.check');
Route::post('/update/download', [UpdateController::class, 'download'])->name('update.download');
Route::post('/update/install', [UpdateController::class, 'install'])->name('update.install');

// Unified settings page
Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
Route::put('/settings', [AppSettingsController::class, 'update'])->name('settings.update');
Route::put('/settings/writing-style', [SettingsController::class, 'updateWritingStyle'])->name('settings.writing-style.update');
Route::put('/settings/prose-pass-rules', [SettingsController::class, 'updateProsePassRules'])->name('settings.prose-pass-rules.update');
Route::put('/settings/proofreading', [SettingsController::class, 'updateProofreadingConfig'])->name('settings.proofreading.update');
Route::put('/books/{book}/settings/custom-dictionary', [SettingsController::class, 'updateCustomDictionary'])->name('books.settings.custom-dictionary.update');
Route::post('/books/{book}/settings/custom-dictionary/seed', [SettingsController::class, 'seedCustomDictionary'])->name('books.settings.custom-dictionary.seed');

// Legacy routes — redirect to unified settings
Route::get('/settings/appearance', fn () => redirect('/settings'))->name('settings.appearance');
Route::get('/settings/ai', fn () => redirect('/settings'))->name('ai-settings.index');
Route::get('/settings/license', fn () => redirect('/settings'))->name('settings.license');
Route::post('/license/activate', [LicenseController::class, 'activate'])->name('license.activate');
Route::post('/license/deactivate', [LicenseController::class, 'deactivate'])->name('license.deactivate');
Route::post('/license/revalidate', [LicenseController::class, 'revalidate'])->name('license.revalidate');

// AI pages — visible to all users (CTAs gated in frontend)
Route::get('/books/{book}/ai/dashboard', [AiDashboardController::class, 'index'])->name('books.ai.dashboard');
Route::get('/books/{book}/ai/editorial-review', [EditorialReviewController::class, 'index'])->name('books.ai.editorial-review.index');

// Pro features — require active licence
Route::middleware('license')->group(function () {
    Route::get('/books/{book}/canvas', [CanvasController::class, 'index'])->name('books.canvas');

    // Plot board
    Route::get('/books/{book}/plot', [PlotController::class, 'index'])->name('books.plot');
    Route::post('/books/{book}/plot/setup-structure', [PlotSetupController::class, 'store'])->name('books.plot.setupStructure');

    Route::post('/books/{book}/acts', [ActController::class, 'store'])->name('acts.store');
    Route::patch('/books/{book}/acts/{act}', [ActController::class, 'update'])->name('acts.update');
    Route::delete('/books/{book}/acts/{act}', [ActController::class, 'destroy'])->name('acts.destroy');

    Route::post('/books/{book}/plot-points', [PlotPointController::class, 'store'])->name('plotPoints.store');
    Route::patch('/books/{book}/plot-points/{plotPoint}', [PlotPointController::class, 'update'])->name('plotPoints.update');
    Route::delete('/books/{book}/plot-points/{plotPoint}', [PlotPointController::class, 'destroy'])->name('plotPoints.destroy');
    Route::post('/books/{book}/plot-points/reorder', [PlotPointController::class, 'reorder'])->name('plotPoints.reorder');
    Route::patch('/books/{book}/plot-points/{plotPoint}/status', [PlotPointController::class, 'updateStatus'])->name('plotPoints.updateStatus');

    Route::post('/books/{book}/plot-points/{plotPoint}/beats', [BeatController::class, 'store'])->name('beats.store');
    Route::patch('/books/{book}/beats/{beat}', [BeatController::class, 'update'])->name('beats.update');
    Route::delete('/books/{book}/beats/{beat}', [BeatController::class, 'destroy'])->name('beats.destroy');
    Route::post('/books/{book}/plot-points/{plotPoint}/beats/reorder', [BeatController::class, 'reorder'])->name('beats.reorder');
    Route::patch('/books/{book}/beats/{beat}/move', [BeatController::class, 'move'])->name('beats.move');
    Route::patch('/books/{book}/beats/{beat}/status', [BeatController::class, 'updateStatus'])->name('beats.updateStatus');
    Route::post('/books/{book}/beats/{beat}/chapters', [BeatController::class, 'linkChapter'])->name('beats.chapters.link');
    Route::delete('/books/{book}/beats/{beat}/chapters/{chapter}', [BeatController::class, 'unlinkChapter'])->name('beats.chapters.unlink');

    Route::post('/books/{book}/plot-connections', [PlotPointConnectionController::class, 'store'])->name('plotConnections.store');
    Route::delete('/books/{book}/plot-connections/{plotPointConnection}', [PlotPointConnectionController::class, 'destroy'])->name('plotConnections.destroy');

    Route::post('/books/{book}/ai/prepare', [AiPreparationController::class, 'start'])->name('books.ai.prepare');
    Route::get('/books/{book}/ai/prepare/status', [AiPreparationController::class, 'status'])->name('books.ai.prepare.status');
    Route::post('/books/{book}/ai/prepare/retry', [AiPreparationController::class, 'retry'])->name('books.ai.prepare.retry');

    Route::put('/settings/ai/{provider}', [AiSettingsController::class, 'update'])->name('ai-settings.update');
    Route::delete('/settings/ai/{provider}/key', [AiSettingsController::class, 'deleteKey'])->name('ai-settings.delete-key');
    Route::post('/settings/ai/{provider}/test', [AiSettingsController::class, 'test'])->name('ai-settings.test');

    Route::post('/books/{book}/ai/analyze', [AiController::class, 'analyze'])->name('books.ai.analyze');
    Route::post('/books/{book}/ai/extract-characters/{chapter}', [AiController::class, 'extractCharacters'])->name('books.ai.extractCharacters');
    Route::post('/books/{book}/ai/next-chapter', [AiController::class, 'nextChapter'])->name('books.ai.nextChapter');
    Route::post('/books/{book}/ai/embed', [AiController::class, 'embed'])->name('books.ai.embed');
    Route::post('/books/{book}/chapters/{chapter}/ai/revise', [AiController::class, 'revise'])->name('chapters.ai.revise');
    Route::post('/books/{book}/chapters/{chapter}/ai/beautify', [AiController::class, 'beautify'])->name('chapters.ai.beautify');
    Route::post('/books/{book}/chapters/{chapter}/ai/analyze-chapter', [AiController::class, 'analyzeChapter'])->name('chapters.ai.analyzeChapter');
    Route::get('/books/{book}/chapters/{chapter}/ai/analysis-status', [AiController::class, 'chapterAnalysisStatus'])->name('chapters.ai.analysisStatus');
    Route::post('/books/{book}/ai/chat', [AiController::class, 'chat'])->name('books.ai.chat');
    Route::get('/books/{book}/ai/conversations/{conversation}/messages', [AiConversationController::class, 'messages'])->name('books.ai.conversations.messages');
    Route::delete('/books/{book}/ai/conversations/{conversation}', [AiConversationController::class, 'destroy'])->name('books.ai.conversations.destroy');
    Route::post('/books/{book}/ai/beautify-all', [AiController::class, 'beautifyAll'])->name('books.ai.beautifyAll');
    Route::post('/books/{book}/ai/revise-all', [AiController::class, 'reviseAll'])->name('books.ai.reviseAll');
    Route::get('/books/{book}/ai/bulk-revision-status', [AiController::class, 'bulkRevisionStatus'])->name('books.ai.bulkRevisionStatus');
    Route::post('/books/{book}/ai/reset-usage', [AiController::class, 'resetUsage'])->name('books.ai.resetUsage');

    Route::post('/books/{book}/ai/editorial-review', [EditorialReviewController::class, 'store'])->name('books.ai.editorial-review.store');
    Route::get('/books/{book}/ai/editorial-review/{review}', [EditorialReviewController::class, 'show'])->name('books.ai.editorial-review.show');
    Route::get('/books/{book}/ai/editorial-review/{review}/progress', [EditorialReviewController::class, 'progress'])->name('books.ai.editorial-review.progress');
    Route::post('/books/{book}/ai/editorial-review/{review}/chat', [EditorialReviewController::class, 'chat'])->name('books.ai.editorial-review.chat');
    Route::post('/books/{book}/ai/editorial-review/{review}/toggle-finding', [EditorialReviewController::class, 'toggleFinding'])->name('books.ai.editorial-review.toggle-finding');

    Route::post('/books/{book}/settings/writing-style/regenerate', [BookSettingsController::class, 'regenerateWritingStyle'])->name('books.settings.writing-style.regenerate');

    Route::post('/books/{book}/plot-coach/stream', [PlotCoachController::class, 'stream'])->name('books.plotCoach.stream');
    Route::get('/books/{book}/plot-coach/sessions', [PlotCoachController::class, 'sessionIndex'])->name('books.plotCoach.sessions.index');
    Route::get('/books/{book}/plot-coach/sessions/{session}', [PlotCoachController::class, 'sessionShow'])->name('books.plotCoach.sessions.show');
    Route::patch('/books/{book}/plot-coach/sessions/{session}/archive', [PlotCoachController::class, 'sessionArchive'])->name('books.plotCoach.sessions.archive');
    Route::patch('/books/{book}/plot-coach/sessions/{session}/mode', [PlotCoachController::class, 'sessionMode'])->name('books.plotCoach.sessions.mode');

    Route::post('/books/{book}/plot/ai/health', [PlotAiController::class, 'runPlotHealth'])->name('books.plot.ai.health');
    Route::post('/books/{book}/plot/ai/holes', [PlotAiController::class, 'detectPlotHoles'])->name('books.plot.ai.holes');
    Route::post('/books/{book}/plot/ai/beats', [PlotAiController::class, 'suggestBeats'])->name('books.plot.ai.beats');
    Route::post('/books/{book}/plot/ai/tension', [PlotAiController::class, 'generateTensionArc'])->name('books.plot.ai.tension');
    Route::get('/books/{book}/plot/ai/status', [PlotAiController::class, 'analysisStatus'])->name('books.plot.ai.status');
});

// Book-level settings — legacy GET routes redirect to unified settings
Route::get('/books/{book}/settings/writing-style', fn () => redirect('/settings'))->name('books.settings.writing-style');
Route::get('/books/{book}/settings/prose-pass-rules', fn () => redirect('/settings'))->name('books.settings.prose-pass-rules');
Route::get('/books/{book}/settings/export', [BookSettingsController::class, 'export'])->name('books.settings.export');
Route::post('/books/{book}/settings/export', [BookSettingsController::class, 'doExport'])->name('books.settings.export.run');
Route::post('/books/{book}/export/preview', [BookSettingsController::class, 'previewPdf'])->name('books.export.preview');

// Publish page — book metadata and matter content
Route::get('/books/{book}/publish', [PublishController::class, 'show'])->name('books.publish');
Route::put('/books/{book}/publish', [PublishController::class, 'update'])->name('books.publish.update');
Route::post('/books/{book}/publish/cover', [PublishController::class, 'uploadCover'])->name('books.publish.cover');
Route::delete('/books/{book}/publish/cover', [PublishController::class, 'deleteCover'])->name('books.publish.cover.delete');
Route::put('/books/{book}/publish/epilogue', [PublishController::class, 'updateEpilogue'])->name('books.publish.epilogue');
Route::get('/books/{book}/publish/cover', [PublishController::class, 'serveCover'])->name('books.publish.cover.serve');
