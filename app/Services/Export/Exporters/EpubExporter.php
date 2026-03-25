<?php

namespace App\Services\Export\Exporters;

use App\Contracts\Exporter;
use App\Contracts\ExportTemplate;
use App\Enums\BackMatterType;
use App\Enums\FrontMatterType;
use App\Models\Book;
use App\Models\Chapter;
use App\Services\Export\ContentPreparer;
use App\Services\Export\ExportOptions;
use App\Services\Export\ExportService;
use App\Services\Export\FontService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class EpubExporter implements Exporter
{
    public function __construct(
        private ContentPreparer $contentPreparer,
        private FontService $fontService,
        private ExportTemplate $template,
    ) {}

    public function export(Book $book, Collection $chapters, ExportOptions $options): string
    {
        $filename = ExportService::tempPath('epub');
        $uuid = $this->generateUuid($book);

        $zip = new ZipArchive;
        $zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // mimetype must be first entry, uncompressed
        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->setCompressionName('mimetype', ZipArchive::CM_STORE);

        $this->addContainerXml($zip);
        $this->addStylesheet($zip, $options);
        $this->addFonts($zip, $options);

        $coverInfo = $this->addCoverImage($zip, $options);

        // Front matter files
        $frontMatterFiles = $this->addFrontMatter($zip, $book, $chapters, $options);

        $chapterFiles = $this->addChapters($zip, $chapters, $options);

        // Back matter files
        $backMatterFiles = $this->addBackMatter($zip, $book, $options);

        if ($options->includeTableOfContents && ! in_array(FrontMatterType::Toc->value, $options->frontMatter)) {
            $this->addTocXhtml($zip, $chapters, $frontMatterFiles, $backMatterFiles);
        }
        $this->addTocNcx($zip, $book, $chapters, $frontMatterFiles, $backMatterFiles, $uuid, $coverInfo);
        $this->addContentOpf($zip, $book, $chapters, $frontMatterFiles, $chapterFiles, $backMatterFiles, $options, $uuid, $coverInfo);

        $zip->close();

        return $filename;
    }

    private function addContainerXml(ZipArchive $zip): void
    {
        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
          <rootfiles>
            <rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/>
          </rootfiles>
        </container>
        XML;

        $zip->addFromString('META-INF/container.xml', $xml);
    }

    private function addStylesheet(ZipArchive $zip, ExportOptions $options): void
    {
        $pairing = $options->fontPairing ?? $this->template->defaultFontPairing();
        $fontFace = $this->fontService->epubFontFaceCssForPairing($pairing);
        $css = $this->template->epubCss($fontFace, $pairing);
        $css .= "\n".$this->template->sceneBreakCss();

        if ($options->dropCaps) {
            $css .= "\n".$this->template->dropCapCss();
        }

        $zip->addFromString('OEBPS/Styles/stylesheet.css', $css);
    }

    private function addFonts(ZipArchive $zip, ExportOptions $options): void
    {
        $pairing = $options->fontPairing ?? $this->template->defaultFontPairing();

        if (! $this->fontService->fontsAvailableForPairing($pairing)) {
            return;
        }

        foreach ($this->fontService->epubFontFilesForPairing($pairing) as $font) {
            $zip->addFile($font['path'], 'OEBPS/Fonts/'.$font['filename']);
        }
    }

    /**
     * Embed the cover image and create a cover XHTML page.
     *
     * @return array{imageFile: string, mimeType: string, xhtmlId: string, xhtmlFile: string}|null
     */
    private function addCoverImage(ZipArchive $zip, ExportOptions $options): ?array
    {
        if (! $options->includeCover || empty($options->coverImagePath)) {
            return null;
        }

        $absolutePath = Storage::disk('local')->path($options->coverImagePath);

        if (! file_exists($absolutePath)) {
            return null;
        }

        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $mimeType = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'image/jpeg',
        };

        $zip->addFile($absolutePath, "OEBPS/Images/cover.{$ext}");

        $body = <<<HTML
        <div style="text-align: center; height: 100%;">
          <img src="../Images/cover.{$ext}" alt="Cover" style="max-width: 100%; max-height: 100%;" />
        </div>
        HTML;

        $xhtml = $this->wrapXhtml('Cover', $body);
        $zip->addFromString('OEBPS/Text/cover.xhtml', $xhtml);

        return [
            'imageFile' => "cover.{$ext}",
            'mimeType' => $mimeType,
            'xhtmlId' => 'cover',
            'xhtmlFile' => 'cover.xhtml',
        ];
    }

    /**
     * @return array<int, array{id: string, file: string, label: string}>
     */
    private function addFrontMatter(ZipArchive $zip, Book $book, Collection $chapters, ExportOptions $options): array
    {
        $files = [];

        foreach ($options->frontMatter as $item) {
            match ($item) {
                FrontMatterType::TitlePage->value => $files[] = $this->addTitlePage($zip, $book),
                FrontMatterType::Copyright->value => $files[] = $this->addCopyrightPage($zip, $book, $options->copyrightText),
                FrontMatterType::Dedication->value => $files[] = $this->addDedicationPage($zip, $options->dedicationText),
                FrontMatterType::Epigraph->value => $files[] = $this->addEpigraphPage($zip, $options->epigraphText, $options->epigraphAttribution),
                FrontMatterType::Toc->value => $files[] = $this->addTocAsFrontMatter($zip, $chapters, $options),
                default => null,
            };
        }

        return array_filter($files);
    }

    /**
     * @return array<int, array{id: string, file: string, label: string}>
     */
    private function addBackMatter(ZipArchive $zip, Book $book, ExportOptions $options): array
    {
        $matterConfig = [
            BackMatterType::Acknowledgments->value => ['epubType' => 'acknowledgments', 'heading' => 'Acknowledgments', 'text' => $options->acknowledgmentText],
            BackMatterType::AboutAuthor->value => ['epubType' => 'contributors', 'heading' => 'About the Author', 'text' => $options->aboutAuthorText],
            BackMatterType::AlsoBy->value => ['epubType' => 'appendix', 'heading' => 'Also By '.$book->author, 'text' => $options->alsoByText],
        ];

        $files = [];

        foreach ($options->backMatter as $item) {
            if (isset($matterConfig[$item])) {
                $cfg = $matterConfig[$item];
                $files[] = $this->addMatterPage($zip, $item, $cfg['epubType'], $cfg['heading'], $cfg['text']);
            }
        }

        // Handle epilogue separately — it's a chapter rendered as back matter
        if (in_array(BackMatterType::Epilogue->value, $options->backMatter)) {
            $epilogueChapter = ExportService::resolveEpilogueChapter($book);

            if ($epilogueChapter) {
                $files[] = $this->addEpilogueChapter($zip, $epilogueChapter, $options);
            }
        }

        return array_filter($files);
    }

    /**
     * @return array{id: string, file: string, label: string}
     */
    private function addTitlePage(ZipArchive $zip, Book $book): array
    {
        $title = htmlspecialchars($book->title, ENT_XML1, 'UTF-8');
        $author = htmlspecialchars($book->author ?? '', ENT_XML1, 'UTF-8');

        $authorBlock = $author !== '' ? "<p class=\"author\">{$author}</p>" : '';

        $body = <<<HTML
        <section epub:type="titlepage" class="title-page">
          <h1>{$title}</h1>
          {$authorBlock}
        </section>
        HTML;

        $xhtml = $this->wrapXhtml($title, $body);
        $zip->addFromString('OEBPS/Text/title-page.xhtml', $xhtml);

        return ['id' => 'title-page', 'file' => 'title-page.xhtml', 'label' => $title];
    }

    /**
     * @return array{id: string, file: string, label: string}
     */
    private function addCopyrightPage(ZipArchive $zip, Book $book, string $copyrightText = ''): array
    {
        if ($copyrightText !== '') {
            $content = $this->contentPreparer->toMatterXhtml($copyrightText);

            $body = <<<HTML
            <section epub:type="copyright-page" class="copyright-page">
              {$content}
            </section>
            HTML;
        } else {
            $title = htmlspecialchars($book->title, ENT_XML1, 'UTF-8');
            $year = date('Y');

            $body = <<<HTML
            <section epub:type="copyright-page" class="copyright-page">
              <p>Copyright &#169; {$year}</p>
              <p>{$title}</p>
              <p>All rights reserved.</p>
            </section>
            HTML;
        }

        $xhtml = $this->wrapXhtml('Copyright', $body);
        $zip->addFromString('OEBPS/Text/copyright.xhtml', $xhtml);

        return ['id' => 'copyright', 'file' => 'copyright.xhtml', 'label' => 'Copyright'];
    }

    /**
     * @return array{id: string, file: string, label: string}|null
     */
    private function addDedicationPage(ZipArchive $zip, string $text): ?array
    {
        if ($text === '') {
            return null;
        }

        $content = htmlspecialchars($text, ENT_XML1, 'UTF-8');

        $body = <<<HTML
        <section epub:type="dedication" class="dedication-page">
          <p>{$content}</p>
        </section>
        HTML;

        $xhtml = $this->wrapXhtml('Dedication', $body);
        $zip->addFromString('OEBPS/Text/dedication.xhtml', $xhtml);

        return ['id' => 'dedication', 'file' => 'dedication.xhtml', 'label' => 'Dedication'];
    }

    /**
     * @return array{id: string, file: string, label: string}|null
     */
    private function addEpigraphPage(ZipArchive $zip, string $text, string $attribution): ?array
    {
        if ($text === '') {
            return null;
        }

        $content = htmlspecialchars($text, ENT_XML1, 'UTF-8');

        $body = "<section epub:type=\"epigraph\" class=\"epigraph-page\">\n  <p class=\"epigraph-text\">{$content}</p>\n";

        if ($attribution !== '') {
            $attr = htmlspecialchars($attribution, ENT_XML1, 'UTF-8');
            $body .= "  <p class=\"epigraph-attribution\">{$attr}</p>\n";
        }

        $body .= '</section>';

        $xhtml = $this->wrapXhtml('Epigraph', $body);
        $zip->addFromString('OEBPS/Text/epigraph.xhtml', $xhtml);

        return ['id' => 'epigraph', 'file' => 'epigraph.xhtml', 'label' => 'Epigraph'];
    }

    /**
     * @return array{id: string, file: string, label: string}|null
     */
    private function addTocAsFrontMatter(ZipArchive $zip, Collection $chapters, ExportOptions $options): ?array
    {
        if ($chapters->isEmpty()) {
            return null;
        }

        $this->addTocXhtml($zip, $chapters, [], []);

        return ['id' => 'toc', 'file' => '../toc.xhtml', 'label' => 'Table of Contents'];
    }

    /**
     * @return array{id: string, file: string, label: string}
     */
    private function addMatterPage(ZipArchive $zip, string $id, string $epubType, string $heading, string $text): array
    {
        $content = $this->contentPreparer->toMatterXhtml($text);

        $body = <<<HTML
        <section epub:type="{$epubType}">
          <p class="matter-title">{$heading}</p>
          <div class="matter-body">
          {$content}
          </div>
        </section>
        HTML;

        $xhtml = $this->wrapXhtml($heading, $body);
        $zip->addFromString("OEBPS/Text/{$id}.xhtml", $xhtml);

        return ['id' => $id, 'file' => "{$id}.xhtml", 'label' => $heading];
    }

    /**
     * Render an epilogue chapter as a back matter page.
     *
     * @return array{id: string, file: string, label: string}
     */
    private function addEpilogueChapter(ZipArchive $zip, Chapter $chapter, ExportOptions $options): array
    {
        $title = htmlspecialchars($chapter->title, ENT_XML1, 'UTF-8');
        $body = "<h1>{$title}</h1>\n";
        $body .= $this->renderChapterBody($chapter, $options);

        $xhtml = $this->wrapXhtml($title, $body, isChapter: true);
        $zip->addFromString('OEBPS/Text/epilogue.xhtml', $xhtml);

        return ['id' => 'epilogue', 'file' => 'epilogue.xhtml', 'label' => $title];
    }

    /**
     * Render the body content for a chapter: scenes joined by scene breaks, with optional drop cap.
     */
    private function renderChapterBody(mixed $chapter, ExportOptions $options): string
    {
        $sceneBreak = $options->sceneBreakStyle ?? $this->template->defaultSceneBreakStyle();
        $body = '';
        $scenes = $chapter->scenes ?? collect();

        foreach ($scenes as $sceneIndex => $scene) {
            if ($sceneIndex > 0) {
                $body .= $sceneBreak->xhtml()."\n";
            }
            $content = $scene->content ?? '';
            $body .= $this->contentPreparer->toXhtml($content, $sceneBreak);
        }

        if ($options->dropCaps) {
            $body = $this->contentPreparer->addDropCap($body);
        }

        return $body;
    }

    /**
     * @return array<int, string>
     */
    private function addChapters(ZipArchive $zip, Collection $chapters, ExportOptions $options): array
    {
        $chapterFiles = [];
        $currentActId = null;

        foreach ($chapters as $index => $chapter) {
            $num = $this->chapterNum($index);
            $fileName = "chapter-{$num}.xhtml";
            $chapterFiles[] = $fileName;

            $body = '';

            if ($options->includeActBreaks && $chapter->act_id && $chapter->act_id !== $currentActId) {
                $currentActId = $chapter->act_id;
                $actTitle = htmlspecialchars($chapter->act?->title ?? "Act {$chapter->act?->number}", ENT_XML1, 'UTF-8');
                $body .= "<p class=\"act-break\">{$actTitle}</p>\n";
            }

            if ($options->includeChapterTitles) {
                $title = htmlspecialchars($chapter->title, ENT_XML1, 'UTF-8');
                $body .= "<h1>{$title}</h1>\n";
            }

            $body .= $this->renderChapterBody($chapter, $options);

            $chapterTitle = htmlspecialchars($chapter->title, ENT_XML1, 'UTF-8');
            $xhtml = $this->wrapXhtml($chapterTitle, $body, isChapter: true);
            $zip->addFromString("OEBPS/Text/{$fileName}", $xhtml);
        }

        return $chapterFiles;
    }

    /**
     * @param  array<int, array{id: string, file: string, label: string}>  $frontMatterFiles
     * @param  array<int, array{id: string, file: string, label: string}>  $backMatterFiles
     */
    private function addTocXhtml(ZipArchive $zip, Collection $chapters, array $frontMatterFiles, array $backMatterFiles): void
    {
        $items = '';
        foreach ($chapters as $index => $chapter) {
            $num = $this->chapterNum($index);
            $title = htmlspecialchars($chapter->title, ENT_XML1, 'UTF-8');
            $items .= "        <li><a href=\"Text/chapter-{$num}.xhtml\">{$title}</a></li>\n";
        }

        foreach ($backMatterFiles as $matter) {
            $items .= "        <li><a href=\"Text/{$matter['file']}\">{$matter['label']}</a></li>\n";
        }

        $body = <<<HTML
        <nav epub:type="toc" id="toc">
          <h1>Table of Contents</h1>
          <ol>
        {$items}  </ol>
        </nav>
        HTML;

        $xhtml = $this->wrapXhtml('Table of Contents', $body, true);
        $zip->addFromString('OEBPS/toc.xhtml', $xhtml);
    }

    /**
     * @param  array<int, array{id: string, file: string, label: string}>  $frontMatterFiles
     * @param  array<int, array{id: string, file: string, label: string}>  $backMatterFiles
     * @param  array{imageFile: string, mimeType: string, xhtmlId: string, xhtmlFile: string}|null  $coverInfo
     */
    private function addTocNcx(ZipArchive $zip, Book $book, Collection $chapters, array $frontMatterFiles, array $backMatterFiles, string $uuid, ?array $coverInfo = null): void
    {
        $bookTitle = htmlspecialchars($book->title, ENT_XML1, 'UTF-8');
        $navPoints = '';
        $playOrder = 1;

        if ($coverInfo) {
            $navPoints .= <<<XML
                <navPoint id="cover" playOrder="{$playOrder}">
                  <navLabel><text>Cover</text></navLabel>
                  <content src="Text/{$coverInfo['xhtmlFile']}"/>
                </navPoint>

            XML;
            $playOrder++;
        }

        foreach ($frontMatterFiles as $matter) {
            if ($matter['id'] === 'toc') {
                continue;
            }
            $href = "Text/{$matter['file']}";
            $navPoints .= <<<XML
                <navPoint id="{$matter['id']}" playOrder="{$playOrder}">
                  <navLabel><text>{$matter['label']}</text></navLabel>
                  <content src="{$href}"/>
                </navPoint>

            XML;
            $playOrder++;
        }

        foreach ($chapters as $index => $chapter) {
            $num = $this->chapterNum($index);
            $title = htmlspecialchars($chapter->title, ENT_XML1, 'UTF-8');
            $navPoints .= <<<XML
                <navPoint id="chapter-{$num}" playOrder="{$playOrder}">
                  <navLabel><text>{$title}</text></navLabel>
                  <content src="Text/chapter-{$num}.xhtml"/>
                </navPoint>

            XML;
            $playOrder++;
        }

        foreach ($backMatterFiles as $matter) {
            $navPoints .= <<<XML
                <navPoint id="{$matter['id']}" playOrder="{$playOrder}">
                  <navLabel><text>{$matter['label']}</text></navLabel>
                  <content src="Text/{$matter['file']}"/>
                </navPoint>

            XML;
            $playOrder++;
        }

        $ncx = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
          <head>
            <meta name="dtb:uid" content="urn:uuid:{$uuid}"/>
          </head>
          <docTitle><text>{$bookTitle}</text></docTitle>
          <navMap>
        {$navPoints}  </navMap>
        </ncx>
        XML;

        $zip->addFromString('OEBPS/toc.ncx', $ncx);
    }

    /**
     * @param  array<int, array{id: string, file: string, label: string}>  $frontMatterFiles
     * @param  array<int, string>  $chapterFiles
     * @param  array<int, array{id: string, file: string, label: string}>  $backMatterFiles
     * @param  array{imageFile: string, mimeType: string, xhtmlId: string, xhtmlFile: string}|null  $coverInfo
     */
    private function addContentOpf(ZipArchive $zip, Book $book, Collection $chapters, array $frontMatterFiles, array $chapterFiles, array $backMatterFiles, ExportOptions $options, string $uuid, ?array $coverInfo = null): void
    {
        $title = htmlspecialchars($book->title, ENT_XML1, 'UTF-8');
        $author = htmlspecialchars($book->author ?? 'Unknown', ENT_XML1, 'UTF-8');
        $language = htmlspecialchars($book->language ?? 'en', ENT_XML1, 'UTF-8');
        $modified = now()->format('Y-m-d\TH:i:s\Z');

        // Build manifest
        $manifestItems = <<<'XML'
            <item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/>
            <item id="stylesheet" href="Styles/stylesheet.css" media-type="text/css"/>
        XML;

        $hasTocInFrontMatter = in_array(FrontMatterType::Toc->value, $options->frontMatter);
        if ($options->includeTableOfContents || $hasTocInFrontMatter) {
            $manifestItems .= "\n    <item id=\"toc\" href=\"toc.xhtml\" media-type=\"application/xhtml+xml\" properties=\"nav\"/>";
        }

        // Cover image and XHTML page
        if ($coverInfo) {
            $manifestItems .= "\n    <item id=\"cover-image\" href=\"Images/{$coverInfo['imageFile']}\" media-type=\"{$coverInfo['mimeType']}\" properties=\"cover-image\"/>";
            $manifestItems .= "\n    <item id=\"cover\" href=\"Text/{$coverInfo['xhtmlFile']}\" media-type=\"application/xhtml+xml\"/>";
        }

        // Font manifest using pairing-aware method
        $pairing = $options->fontPairing ?? $this->template->defaultFontPairing();
        if ($this->fontService->fontsAvailableForPairing($pairing)) {
            foreach ($this->fontService->epubFontFilesForPairing($pairing) as $index => $font) {
                $fontId = 'font-'.$index;
                $manifestItems .= "\n    <item id=\"{$fontId}\" href=\"Fonts/{$font['filename']}\" media-type=\"font/ttf\"/>";
            }
        }

        // Front matter manifest
        foreach ($frontMatterFiles as $matter) {
            if ($matter['id'] === 'toc') {
                continue; // Already in manifest above
            }
            $manifestItems .= "\n    <item id=\"{$matter['id']}\" href=\"Text/{$matter['file']}\" media-type=\"application/xhtml+xml\"/>";
        }

        foreach ($chapterFiles as $index => $file) {
            $num = $this->chapterNum($index);
            $manifestItems .= "\n    <item id=\"chapter-{$num}\" href=\"Text/{$file}\" media-type=\"application/xhtml+xml\"/>";
        }

        // Back matter manifest
        foreach ($backMatterFiles as $matter) {
            $manifestItems .= "\n    <item id=\"{$matter['id']}\" href=\"Text/{$matter['file']}\" media-type=\"application/xhtml+xml\"/>";
        }

        // Build spine — cover, then front matter, then TOC, then chapters, then back matter
        $spineItems = '';

        if ($coverInfo) {
            $spineItems .= "    <itemref idref=\"cover\"/>\n";
        }

        foreach ($frontMatterFiles as $matter) {
            if ($matter['id'] === 'toc') {
                $spineItems .= "    <itemref idref=\"toc\"/>\n";
            } else {
                $spineItems .= "    <itemref idref=\"{$matter['id']}\"/>\n";
            }
        }

        if (($options->includeTableOfContents && ! $hasTocInFrontMatter)) {
            $spineItems .= "    <itemref idref=\"toc\"/>\n";
        }

        foreach ($chapterFiles as $index => $file) {
            $num = $this->chapterNum($index);
            $spineItems .= "    <itemref idref=\"chapter-{$num}\"/>\n";
        }

        foreach ($backMatterFiles as $matter) {
            $spineItems .= "    <itemref idref=\"{$matter['id']}\"/>\n";
        }

        $opf = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <package xmlns="http://www.idpf.org/2007/opf" unique-identifier="BookId" version="3.0">
          <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
            <dc:identifier id="BookId">urn:uuid:{$uuid}</dc:identifier>
            <dc:title>{$title}</dc:title>
            <dc:creator>{$author}</dc:creator>
            <dc:language>{$language}</dc:language>
            <meta property="dcterms:modified">{$modified}</meta>
          </metadata>
          <manifest>
        {$manifestItems}
          </manifest>
          <spine toc="ncx">
        {$spineItems}  </spine>
        </package>
        XML;

        $zip->addFromString('OEBPS/content.opf', $opf);
    }

    private function wrapXhtml(string $title, string $body, bool $isNav = false, bool $isChapter = false): string
    {
        $bodyAttr = $isChapter ? ' epub:type="bodymatter"' : '';
        $wrappedBody = $isChapter
            ? "<section epub:type=\"chapter\">\n{$body}\n</section>"
            : $body;

        return <<<XHTML
        <?xml version="1.0" encoding="UTF-8"?>
        <!DOCTYPE html>
        <html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" xml:lang="en">
        <head>
          <meta charset="UTF-8"/>
          <title>{$title}</title>
          <link rel="stylesheet" type="text/css" href="../Styles/stylesheet.css"/>
        </head>
        <body{$bodyAttr}>
        {$wrappedBody}
        </body>
        </html>
        XHTML;
    }

    private function chapterNum(int $index): string
    {
        return str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
    }

    private function generateUuid(Book $book): string
    {
        // Deterministic UUID based on book ID for consistency
        return sprintf(
            '%08x-%04x-4%03x-%04x-%012x',
            $book->id,
            ($book->id >> 16) & 0xFFFF,
            $book->id & 0xFFF,
            (($book->id >> 8) & 0x3FFF) | 0x8000,
            $book->id * 2654435761,
        );
    }
}
