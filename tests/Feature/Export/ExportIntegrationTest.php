<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\License;
use App\Models\Scene;
use App\Models\Storyline;
use App\Models\User;
use App\Services\Export\ExportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    License::factory()->create();
    $this->user = User::factory()->create();
    $this->book = Book::factory()->create([
        'title' => 'Test Novel',
        'author' => 'Test Author',
        'language' => 'en',
        'copyright_text' => '© 2026 Test Author',
        'dedication_text' => 'For testing',
        'epigraph_text' => 'To be or not to be',
        'epigraph_attribution' => '— Shakespeare',
        'acknowledgment_text' => 'Thanks to everyone',
        'about_author_text' => 'Test Author writes tests.',
        'also_by_text' => "Book One\nBook Two",
    ]);
    $storyline = Storyline::factory()->for($this->book)->create();
    $chapter = Chapter::factory()->for($this->book)->for($storyline)->create([
        'title' => 'Chapter One',
    ]);
    Scene::factory()->for($chapter)->create([
        'content' => '<p>This is <strong>bold</strong> and <em>italic</em> text.</p><hr><p>After the scene break.</p>',
        'sort_order' => 1,
    ]);
    $this->actingAs($this->user);
});

it('exports EPUB with classic template', function () {
    $response = $this->postJson(route('books.settings.export.run', $this->book), [
        'format' => 'epub',
        'scope' => 'full',
        'template' => 'classic',
        'front_matter' => ['title-page', 'copyright', 'dedication'],
        'back_matter' => ['acknowledgments'],
        'include_chapter_titles' => true,
    ]);

    $response->assertSuccessful();
    $response->assertHeader('content-disposition');
    expect($response->headers->get('content-disposition'))->toContain('test-novel.epub');
});

it('exports EPUB with modern template and custom font pairing', function () {
    $response = $this->postJson(route('books.settings.export.run', $this->book), [
        'format' => 'epub',
        'scope' => 'full',
        'template' => 'modern',
        'font_pairing' => 'modern-mixed',
        'scene_break_style' => 'rule',
        'front_matter' => ['title-page'],
        'back_matter' => [],
    ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-disposition'))->toContain('test-novel.epub');
});

it('exports EPUB with elegant template and drop caps', function () {
    $response = $this->postJson(route('books.settings.export.run', $this->book), [
        'format' => 'epub',
        'scope' => 'full',
        'template' => 'elegant',
        'drop_caps' => true,
        'scene_break_style' => 'flourish',
        'front_matter' => ['title-page', 'epigraph'],
        'back_matter' => ['about-author', 'also-by'],
    ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-disposition'))->toContain('test-novel.epub');
});

it('exports EPUB with all front and back matter', function () {
    $response = $this->postJson(route('books.settings.export.run', $this->book), [
        'format' => 'epub',
        'scope' => 'full',
        'template' => 'classic',
        'front_matter' => ['title-page', 'copyright', 'dedication', 'epigraph', 'toc'],
        'back_matter' => ['acknowledgments', 'about-author', 'also-by'],
        'include_chapter_titles' => true,
    ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-disposition'))->toContain('test-novel.epub');
});

it('exports EPUB with custom scene break style', function () {
    $response = $this->postJson(route('books.settings.export.run', $this->book), [
        'format' => 'epub',
        'scope' => 'full',
        'template' => 'classic',
        'scene_break_style' => 'fleuron',
        'front_matter' => [],
        'back_matter' => [],
    ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-disposition'))->toContain('test-novel.epub');
});

it('exports EPUB with cross-template font pairing override', function () {
    $response = $this->postJson(route('books.settings.export.run', $this->book), [
        'format' => 'epub',
        'scope' => 'full',
        'template' => 'classic',
        'font_pairing' => 'elegant-serif',
        'front_matter' => [],
        'back_matter' => [],
    ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-disposition'))->toContain('test-novel.epub');
});

it('exports DOCX with formatting preserved', function () {
    $response = $this->postJson(route('books.settings.export.run', $this->book), [
        'format' => 'docx',
        'scope' => 'full',
        'front_matter' => ['title-page', 'copyright'],
        'back_matter' => ['acknowledgments'],
        'include_chapter_titles' => true,
    ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-disposition'))->toContain('test-novel.docx');
});

it('exports DOCX with all matter types', function () {
    $response = $this->postJson(route('books.settings.export.run', $this->book), [
        'format' => 'docx',
        'scope' => 'full',
        'front_matter' => ['title-page', 'copyright', 'dedication', 'epigraph'],
        'back_matter' => ['acknowledgments', 'about-author', 'also-by'],
    ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-disposition'))->toContain('test-novel.docx');
});

it('localizes EPUB front and back matter in the book language', function () {
    $this->book->update([
        'language' => 'de',
        'copyright_text' => null,
    ]);

    $path = (new ExportService)->exportToPath($this->book, [
        'format' => 'epub',
        'scope' => 'full',
        'template' => 'classic',
        'front_matter' => ['copyright', 'dedication', 'epigraph', 'toc'],
        'back_matter' => ['acknowledgments', 'about-author', 'also-by'],
    ]);

    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();

    $copyright = $zip->getFromName('OEBPS/Text/copyright.xhtml');
    $dedication = $zip->getFromName('OEBPS/Text/dedication.xhtml');
    $epigraph = $zip->getFromName('OEBPS/Text/epigraph.xhtml');
    $acknowledgments = $zip->getFromName('OEBPS/Text/acknowledgments.xhtml');
    $aboutAuthor = $zip->getFromName('OEBPS/Text/about-author.xhtml');
    $alsoBy = $zip->getFromName('OEBPS/Text/also-by.xhtml');
    $toc = $zip->getFromName('OEBPS/toc.xhtml');
    $navigation = $zip->getFromName('OEBPS/toc.ncx');
    $zip->close();
    @unlink($path);

    expect($copyright)->toContain('Alle Rechte vorbehalten.')
        ->and($dedication)->toContain('<title>Widmung</title>')
        ->and($epigraph)->toContain('<title>Epigraph</title>')
        ->and($acknowledgments)->toContain('<p class="matter-title">Danksagung</p>')
        ->and($aboutAuthor)->toContain('<p class="matter-title">Über den Autor</p>')
        ->and($alsoBy)->toContain('<p class="matter-title">Weitere Werke von Test Author</p>')
        ->and($toc)->toContain('<h1>Inhaltsverzeichnis</h1>')
        ->and($navigation)->toContain('<text>Copyright</text>')
        ->and($navigation)->toContain('<text>Widmung</text>')
        ->and($navigation)->toContain('<text>Epigraph</text>')
        ->and($navigation)->toContain('<text>Danksagung</text>')
        ->and($navigation)->toContain('<text>Über den Autor</text>')
        ->and($navigation)->toContain('<text>Weitere Werke von Test Author</text>');
});

it('localizes DOCX back matter in the book language', function () {
    $this->book->update([
        'language' => 'de',
        'copyright_text' => null,
    ]);

    $path = (new ExportService)->exportToPath($this->book, [
        'format' => 'docx',
        'scope' => 'full',
        'front_matter' => ['copyright'],
        'back_matter' => ['acknowledgments', 'about-author', 'also-by'],
    ]);

    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    $document = $zip->getFromName('word/document.xml');
    $zip->close();
    @unlink($path);

    expect($document)
        ->toContain('Alle Rechte vorbehalten.')
        ->toContain('Danksagung')
        ->toContain('Über den Autor')
        ->toContain('Weitere Werke von Test Author');
});

it('exports TXT format', function () {
    $response = $this->postJson(route('books.settings.export.run', $this->book), [
        'format' => 'txt',
        'scope' => 'full',
        'front_matter' => [],
        'back_matter' => [],
    ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-disposition'))->toContain('test-novel.txt');
});

it('exports with epilogue back matter', function () {
    $storyline = Storyline::factory()->for($this->book)->create();
    $epilogue = Chapter::factory()->for($this->book)->for($storyline)->create([
        'title' => 'Epilogue',
        'is_epilogue' => true,
    ]);
    Scene::factory()->for($epilogue)->create([
        'content' => '<p>The end of the story.</p>',
        'sort_order' => 1,
    ]);

    $response = $this->postJson(route('books.settings.export.run', $this->book), [
        'format' => 'epub',
        'scope' => 'full',
        'template' => 'classic',
        'front_matter' => ['title-page'],
        'back_matter' => ['epilogue', 'acknowledgments'],
    ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-disposition'))->toContain('test-novel.epub');
});

it('exports EPUB with cover image', function () {
    Storage::fake('local');
    $coverPath = 'covers/'.$this->book->id.'/cover.jpg';
    Storage::disk('local')->put($coverPath, UploadedFile::fake()->image('cover.jpg', 2560, 1600)->getContent());
    $this->book->update(['cover_image_path' => $coverPath]);

    $response = $this->postJson(route('books.settings.export.run', $this->book), [
        'format' => 'epub',
        'scope' => 'full',
        'template' => 'classic',
        'include_cover' => true,
        'front_matter' => ['title-page'],
        'back_matter' => [],
    ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-disposition'))->toContain('test-novel.epub');
});

it('validates template must be one of allowed values', function () {
    $response = $this->postJson(route('books.settings.export.run', $this->book), [
        'format' => 'epub',
        'scope' => 'full',
        'template' => 'invalid-template',
        'front_matter' => [],
        'back_matter' => [],
    ]);

    $response->assertJsonValidationErrors('template');
});

it('validates font pairing must be valid enum', function () {
    $response = $this->postJson(route('books.settings.export.run', $this->book), [
        'format' => 'epub',
        'scope' => 'full',
        'font_pairing' => 'nonexistent',
        'front_matter' => [],
        'back_matter' => [],
    ]);

    $response->assertJsonValidationErrors('font_pairing');
});

it('validates scene break style must be valid enum', function () {
    $response = $this->postJson(route('books.settings.export.run', $this->book), [
        'format' => 'epub',
        'scope' => 'full',
        'scene_break_style' => 'nonexistent',
        'front_matter' => [],
        'back_matter' => [],
    ]);

    $response->assertJsonValidationErrors('scene_break_style');
});
