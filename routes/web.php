<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\AiPreparationController;
use App\Http\Controllers\AiSettingsController;
use App\Http\Controllers\AppSettingsController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BookSettingsController;
use App\Http\Controllers\CanvasController;
use App\Http\Controllers\ChapterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\NormalizationController;
use App\Http\Controllers\PlotController;
use App\Http\Controllers\SceneController;
use App\Http\Controllers\StorylineController;
use App\Http\Controllers\TrashController;
use App\Http\Controllers\WikiController;
use App\Http\Controllers\WritingGoalController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BookController::class, 'index'])->name('books.index');
Route::post('/books', [BookController::class, 'store'])->name('books.store');
Route::patch('/books/{book}', [BookController::class, 'update'])->name('books.update');
Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('books.destroy');
Route::post('/books/{book}/duplicate', [BookController::class, 'duplicate'])->name('books.duplicate');
Route::get('/books/{book}/import', [BookController::class, 'import'])->name('books.import');
Route::post('/books/{book}/import/parse', [BookController::class, 'parse'])->name('books.import.parse');
Route::post('/books/{book}/import/confirm', [BookController::class, 'confirmImport'])->name('books.import.confirm');

Route::get('/books/{book}/dashboard', [DashboardController::class, 'show'])->name('books.dashboard');
Route::put('/books/{book}/writing-goal', [WritingGoalController::class, 'update'])->name('books.writing-goal.update');
Route::patch('/books/{book}/milestone/dismiss', [DashboardController::class, 'dismissMilestone'])->name('books.milestone.dismiss');
Route::get('/books/{book}/wiki', [WikiController::class, 'index'])->name('books.wiki');
Route::get('/books/{book}/plot', [PlotController::class, 'index'])->name('books.plot');
Route::get('/books/{book}/editor', [ChapterController::class, 'editor'])->name('books.editor');
Route::post('/books/{book}/chapters', [ChapterController::class, 'store'])->name('chapters.store');
Route::get('/books/{book}/chapters/{chapter}', [ChapterController::class, 'show'])->name('chapters.show');
Route::patch('/books/{book}/chapters/{chapter}/title', [ChapterController::class, 'updateTitle'])->name('chapters.updateTitle');
Route::put('/books/{book}/chapters/{chapter}/content', [ChapterController::class, 'updateContent'])->name('chapters.updateContent');
Route::get('/books/{book}/chapters/{chapter}/versions', [ChapterController::class, 'versions'])->name('chapters.versions');
Route::post('/books/{book}/chapters/{chapter}/versions', [ChapterController::class, 'createSnapshot'])->name('chapters.createSnapshot');
Route::post('/books/{book}/chapters/{chapter}/versions/{version}/restore', [ChapterController::class, 'restoreVersion'])->name('chapters.restoreVersion');
Route::delete('/books/{book}/chapters/{chapter}/versions/{version}', [ChapterController::class, 'destroyVersion'])->name('chapters.destroyVersion');
Route::post('/books/{book}/chapters/{chapter}/versions/{version}/accept', [ChapterController::class, 'acceptVersion'])->name('chapters.acceptVersion');
Route::post('/books/{book}/chapters/{chapter}/versions/{version}/reject', [ChapterController::class, 'rejectVersion'])->name('chapters.rejectVersion');
Route::patch('/books/{book}/chapters/{chapter}/notes', [ChapterController::class, 'updateNotes'])->name('chapters.updateNotes');
Route::post('/books/{book}/chapters/{chapter}/split', [ChapterController::class, 'split'])->name('chapters.split');
Route::delete('/books/{book}/chapters/{chapter}', [ChapterController::class, 'destroy'])->name('chapters.destroy');
Route::patch('/books/{book}/chapters/{chapter}/status', [ChapterController::class, 'updateStatus'])->name('chapters.updateStatus');
Route::post('/books/{book}/chapters/reorder', [ChapterController::class, 'reorder'])->name('chapters.reorder');

Route::post('/books/{book}/chapters/{chapter}/scenes', [SceneController::class, 'store'])->name('scenes.store');
Route::put('/books/{book}/chapters/{chapter}/scenes/{scene}/content', [SceneController::class, 'updateContent'])->name('scenes.updateContent');
Route::patch('/books/{book}/chapters/{chapter}/scenes/{scene}/title', [SceneController::class, 'updateTitle'])->name('scenes.updateTitle');
Route::post('/books/{book}/chapters/{chapter}/scenes/reorder', [SceneController::class, 'reorder'])->name('scenes.reorder');
Route::delete('/books/{book}/chapters/{chapter}/scenes/{scene}', [SceneController::class, 'destroy'])->name('scenes.destroy');

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

// App settings
Route::get('/settings', fn () => redirect('/settings/appearance'));
Route::get('/settings/appearance', [AppSettingsController::class, 'appearance'])->name('settings.appearance');
Route::put('/settings', [AppSettingsController::class, 'update'])->name('settings.update');

// AI settings index (free — licence activation form lives here)
Route::get('/settings/ai', [AiSettingsController::class, 'index'])->name('ai-settings.index');

// License
Route::get('/settings/license', [LicenseController::class, 'index'])->name('settings.license');
Route::post('/license/activate', [LicenseController::class, 'activate'])->name('license.activate');
Route::post('/license/deactivate', [LicenseController::class, 'deactivate'])->name('license.deactivate');

// Pro features — require active licence
Route::middleware('license')->group(function () {
    Route::get('/books/{book}/canvas', [CanvasController::class, 'index'])->name('books.canvas');

    Route::post('/books/{book}/ai/prepare', [AiPreparationController::class, 'start'])->name('books.ai.prepare');
    Route::get('/books/{book}/ai/prepare/status', [AiPreparationController::class, 'status'])->name('books.ai.prepare.status');

    Route::put('/settings/ai/{provider}', [AiSettingsController::class, 'update'])->name('ai-settings.update');
    Route::post('/settings/ai/{provider}/test', [AiSettingsController::class, 'test'])->name('ai-settings.test');

    Route::post('/books/{book}/ai/analyze', [AiController::class, 'analyze'])->name('books.ai.analyze');
    Route::post('/books/{book}/ai/extract-characters/{chapter}', [AiController::class, 'extractCharacters'])->name('books.ai.extractCharacters');
    Route::post('/books/{book}/ai/next-chapter', [AiController::class, 'nextChapter'])->name('books.ai.nextChapter');
    Route::post('/books/{book}/ai/embed', [AiController::class, 'embed'])->name('books.ai.embed');
    Route::post('/books/{book}/chapters/{chapter}/ai/revise', [AiController::class, 'revise'])->name('chapters.ai.revise');
    Route::post('/books/{book}/chapters/{chapter}/ai/beautify', [AiController::class, 'beautify'])->name('chapters.ai.beautify');

    Route::post('/books/{book}/settings/writing-style/regenerate', [BookSettingsController::class, 'regenerateWritingStyle'])->name('books.settings.writing-style.regenerate');
});

// Book-level settings
Route::get('/books/{book}/settings/writing-style', [BookSettingsController::class, 'writingStyle'])->name('books.settings.writing-style');
Route::put('/books/{book}/settings/writing-style', [BookSettingsController::class, 'updateWritingStyle'])->name('books.settings.writing-style.update');
Route::get('/books/{book}/settings/prose-pass-rules', [BookSettingsController::class, 'prosePassRules'])->name('books.settings.prose-pass-rules');
Route::put('/books/{book}/settings/prose-pass-rules', [BookSettingsController::class, 'updateProsePassRules'])->name('books.settings.prose-pass-rules.update');
Route::get('/books/{book}/settings/export', [BookSettingsController::class, 'export'])->name('books.settings.export');
Route::post('/books/{book}/settings/export', [BookSettingsController::class, 'doExport'])->name('books.settings.export.run');
