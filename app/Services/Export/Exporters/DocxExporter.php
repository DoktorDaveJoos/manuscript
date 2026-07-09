<?php

namespace App\Services\Export\Exporters;

use App\Contracts\Exporter;
use App\Contracts\ExportTemplate;
use App\Enums\DocxLayout;
use App\Models\Book;
use App\Services\Export\ContentPreparer;
use App\Services\Export\ExportOptions;
use App\Services\Export\ExportService;
use Illuminate\Support\Collection;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

class DocxExporter implements Exporter
{
    public function __construct(
        private ContentPreparer $contentPreparer,
        private ExportTemplate $template,
    ) {}

    public function export(Book $book, Collection $chapters, ExportOptions $options): string
    {
        $phpWord = new PhpWord;

        // Both layouts use Times New Roman 12 pt; they differ in line spacing,
        // body alignment, and page geometry (see DocxLayout).
        $normseite = $options->docxLayout === DocxLayout::Normseite;
        $lineHeight = $normseite ? 1.5 : 2.0;
        $bodyAlignment = $normseite ? ['alignment' => Jc::BOTH] : [];

        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(12);

        $phpWord->addParagraphStyle('Normal', [
            'lineHeight' => $lineHeight,
            'spaceAfter' => 0,
            'spaceBefore' => 0,
            'indentation' => ['firstLine' => 720], // 0.5 inch in twips
            ...$bodyAlignment,
        ]);

        $phpWord->addParagraphStyle('NoIndent', [
            'lineHeight' => $lineHeight,
            'spaceAfter' => 0,
            'spaceBefore' => 0,
            ...$bodyAlignment,
        ]);

        $phpWord->addParagraphStyle('ChapterTitle', [
            'lineHeight' => $lineHeight,
            'spaceAfter' => 240,
            'spaceBefore' => 2400,
            'alignment' => Jc::CENTER,
        ]);

        $phpWord->addParagraphStyle('SceneBreak', [
            'lineHeight' => $lineHeight,
            'spaceAfter' => 240,
            'spaceBefore' => 240,
            'alignment' => Jc::CENTER,
        ]);

        $phpWord->addParagraphStyle('MatterTitle', [
            'lineHeight' => $lineHeight,
            'spaceAfter' => 480,
            'spaceBefore' => 2400,
            'alignment' => Jc::CENTER,
        ]);

        $phpWord->addParagraphStyle('Centered', [
            'lineHeight' => $lineHeight,
            'spaceAfter' => 0,
            'spaceBefore' => 0,
            'alignment' => Jc::CENTER,
        ]);

        // Normseite: DIN A4 with 2.5 cm top/left and 4.5 cm bottom/right
        // correction margins — with 1.5 spacing that yields ~30 lines per page.
        // Manuscript: 1-inch margins on the default page.
        $section = $phpWord->addSection($normseite ? [
            // Explicit integer twips — PhpWord's paperSize => 'A4' emits
            // fractional pgSz values, which some validators reject.
            'pageSizeW' => 11906,
            'pageSizeH' => 16838,
            'marginTop' => 1417,
            'marginBottom' => 2551,
            'marginLeft' => 1417,
            'marginRight' => 2551,
        ] : [
            'marginTop' => 1440,
            'marginBottom' => 1440,
            'marginLeft' => 1440,
            'marginRight' => 1440,
        ]);

        // Front matter
        $this->addFrontMatter($section, $book, $options);

        // Chapters
        $isFirstChapter = true;
        $currentActId = null;

        foreach ($chapters as $chapter) {
            if ($options->includeActBreaks && $chapter->act_id && $chapter->act_id !== $currentActId) {
                $currentActId = $chapter->act_id;
                if (! $isFirstChapter) {
                    $section->addPageBreak();
                }
                $section->addText(
                    htmlspecialchars($chapter->act?->title ?? "Act {$chapter->act?->number}"),
                    ['bold' => true, 'size' => 18],
                    'MatterTitle',
                );
            }

            if (! $isFirstChapter) {
                $section->addPageBreak();
            }
            $isFirstChapter = false;

            if ($options->chapterHeading->showsTitle() && $chapter->title) {
                $section->addText(
                    htmlspecialchars($chapter->title),
                    ['bold' => true, 'size' => 16],
                    'ChapterTitle',
                );
            }

            $this->addChapterContent($section, $chapter);
        }

        // Back matter
        $this->addBackMatter($section, $book, $options);

        $filename = ExportService::tempPath('docx');
        $phpWord->save($filename);

        return $filename;
    }

    private function addFrontMatter(Section $section, Book $book, ExportOptions $options): void
    {
        foreach ($options->frontMatter as $item) {
            match ($item) {
                'title-page' => $this->addTitlePage($section, $book),
                'copyright' => $this->addCopyrightPage($section, $book, $options),
                'dedication' => $this->addDedicationPage($section, $options),
                'epigraph' => $this->addEpigraphPage($section, $options),
                'prologue' => $this->addPrologueMatter($section, $book),
                default => null,
            };
        }
    }

    private function addTitlePage(Section $section, Book $book): void
    {
        $section->addTextBreak(8);
        $section->addText(
            htmlspecialchars($book->title),
            ['bold' => true, 'size' => 24],
            ['alignment' => Jc::CENTER],
        );
        $section->addTextBreak(2);
        $section->addText(
            htmlspecialchars($book->author ?? ''),
            ['size' => 16],
            ['alignment' => Jc::CENTER],
        );
        $section->addPageBreak();
    }

    private function addCopyrightPage(Section $section, Book $book, ExportOptions $options): void
    {
        $section->addTextBreak(12);
        $locale = $book->language ?? config('app.fallback_locale', 'en');
        $text = $options->copyrightText !== ''
            ? $options->copyrightText
            : __('Copyright', [], $locale).' © '.date('Y')."\n{$book->title}\n".__('All rights reserved.', [], $locale);
        foreach (explode("\n", $text) as $line) {
            if (trim($line) !== '') {
                $section->addText(htmlspecialchars(trim($line)), ['size' => 10], ['alignment' => Jc::CENTER]);
            }
        }
        $section->addPageBreak();
    }

    private function addDedicationPage(Section $section, ExportOptions $options): void
    {
        if ($options->dedicationText === '') {
            return;
        }
        $section->addTextBreak(8);
        $section->addText(
            htmlspecialchars($options->dedicationText),
            ['italic' => true, 'size' => 12],
            ['alignment' => Jc::CENTER],
        );
        $section->addPageBreak();
    }

    private function addEpigraphPage(Section $section, ExportOptions $options): void
    {
        if ($options->epigraphText === '') {
            return;
        }
        $section->addTextBreak(8);
        $section->addText(
            htmlspecialchars($options->epigraphText),
            ['italic' => true, 'size' => 12],
            ['alignment' => Jc::CENTER],
        );
        if ($options->epigraphAttribution !== '') {
            $section->addTextBreak(1);
            $section->addText(
                htmlspecialchars($options->epigraphAttribution),
                ['size' => 11],
                ['alignment' => Jc::CENTER],
            );
        }
        $section->addPageBreak();
    }

    private function addPrologueMatter(Section $section, Book $book): void
    {
        $prologue = ExportService::resolvePrologueChapter($book);
        if (! $prologue) {
            return;
        }
        $locale = $book->language ?? config('app.fallback_locale', 'en');
        $section->addText(htmlspecialchars(__('Prologue', [], $locale)), ['bold' => true, 'size' => 16], 'ChapterTitle');
        $this->addChapterContent($section, $prologue);
        $section->addPageBreak();
    }

    private function addChapterContent(Section $section, mixed $chapter): void
    {
        $content = $chapter->getContentWithSceneBreaks();
        $segments = $this->contentPreparer->toFormattedSegments($content);

        $isFirstParagraph = true;
        $currentRun = null;

        foreach ($segments as $segment) {
            if ($segment['type'] === 'scene-break') {
                $section->addText('* * *', ['italic' => true], 'SceneBreak');
                $isFirstParagraph = true;
                $currentRun = null;
            } elseif ($segment['type'] === 'paragraph-start') {
                $style = $isFirstParagraph ? 'NoIndent' : 'Normal';
                $currentRun = $section->addTextRun($style);
                $isFirstParagraph = false;
            } elseif ($segment['type'] === 'text' && $currentRun) {
                $fontStyle = [];
                if (! empty($segment['bold'])) {
                    $fontStyle['bold'] = true;
                }
                if (! empty($segment['italic'])) {
                    $fontStyle['italic'] = true;
                }
                if (! empty($segment['strikethrough'])) {
                    $fontStyle['strikethrough'] = true;
                }
                $currentRun->addText(htmlspecialchars($segment['text']), $fontStyle);
            }
        }
    }

    private function addBackMatter(Section $section, Book $book, ExportOptions $options): void
    {
        $locale = $book->language ?? config('app.fallback_locale', 'en');

        foreach ($options->backMatter as $item) {
            match ($item) {
                'epilogue' => $this->addEpilogueMatter($section, $book),
                'acknowledgments' => $this->addTextMatter($section, __('Acknowledgments', [], $locale), $options->acknowledgmentText),
                'about-author' => $this->addTextMatter($section, __('About the Author', [], $locale), $options->aboutAuthorText),
                'also-by' => $this->addAlsoByMatter($section, $book, $options, $locale),
                default => null,
            };
        }
    }

    private function addEpilogueMatter(Section $section, Book $book): void
    {
        $epilogue = ExportService::resolveEpilogueChapter($book);
        if (! $epilogue) {
            return;
        }
        $locale = $book->language ?? config('app.fallback_locale', 'en');
        $section->addPageBreak();
        $section->addText(htmlspecialchars(__('Epilogue', [], $locale)), ['bold' => true, 'size' => 16], 'ChapterTitle');
        $this->addChapterContent($section, $epilogue);
    }

    private function addTextMatter(Section $section, string $heading, string $text): void
    {
        if ($text === '') {
            return;
        }
        $section->addPageBreak();
        $section->addText(htmlspecialchars($heading), ['bold' => true, 'size' => 16], 'MatterTitle');
        foreach (explode("\n", $text) as $line) {
            if (trim($line) !== '') {
                $section->addText(htmlspecialchars(trim($line)), ['size' => 12], 'NoIndent');
            }
        }
    }

    private function addAlsoByMatter(Section $section, Book $book, ExportOptions $options, string $locale): void
    {
        if ($options->alsoByText === '') {
            return;
        }
        $section->addPageBreak();
        $section->addText(
            htmlspecialchars(__('Also By :author', ['author' => $book->author ?? ''], $locale)),
            ['bold' => true, 'size' => 16],
            'MatterTitle',
        );
        foreach (explode("\n", $options->alsoByText) as $line) {
            if (trim($line) !== '') {
                $section->addText(htmlspecialchars(trim($line)), ['italic' => true, 'size' => 12], 'Centered');
            }
        }
    }
}
