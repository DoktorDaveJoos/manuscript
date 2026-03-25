<?php

namespace App\Services\Export\Exporters;

use App\Contracts\Exporter;
use App\Contracts\ExportTemplate;
use App\Models\Book;
use App\Services\Export\ContentPreparer;
use App\Services\Export\ExportOptions;
use App\Services\Export\ExportService;
use Illuminate\Support\Collection;
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

        // Industry manuscript format
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(12);

        $phpWord->addParagraphStyle('Normal', [
            'lineHeight' => 2.0,
            'spaceAfter' => 0,
            'spaceBefore' => 0,
            'indentation' => ['firstLine' => 720], // 0.5 inch in twips
        ]);

        $phpWord->addParagraphStyle('NoIndent', [
            'lineHeight' => 2.0,
            'spaceAfter' => 0,
            'spaceBefore' => 0,
        ]);

        $phpWord->addParagraphStyle('ChapterTitle', [
            'lineHeight' => 2.0,
            'spaceAfter' => 240,
            'spaceBefore' => 2400,
            'alignment' => Jc::CENTER,
        ]);

        $phpWord->addParagraphStyle('SceneBreak', [
            'lineHeight' => 2.0,
            'spaceAfter' => 240,
            'spaceBefore' => 240,
            'alignment' => Jc::CENTER,
        ]);

        $phpWord->addParagraphStyle('MatterTitle', [
            'lineHeight' => 2.0,
            'spaceAfter' => 480,
            'spaceBefore' => 2400,
            'alignment' => Jc::CENTER,
        ]);

        $phpWord->addParagraphStyle('Centered', [
            'lineHeight' => 2.0,
            'spaceAfter' => 0,
            'spaceBefore' => 0,
            'alignment' => Jc::CENTER,
        ]);

        // Create section with 1-inch margins
        $section = $phpWord->addSection([
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

            if ($options->includeChapterTitles && $chapter->title) {
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

    private function addFrontMatter(\PhpOffice\PhpWord\Element\Section $section, Book $book, ExportOptions $options): void
    {
        foreach ($options->frontMatter as $item) {
            match ($item) {
                'title-page' => $this->addTitlePage($section, $book),
                'copyright' => $this->addCopyrightPage($section, $book, $options),
                'dedication' => $this->addDedicationPage($section, $options),
                'epigraph' => $this->addEpigraphPage($section, $options),
                default => null,
            };
        }
    }

    private function addTitlePage(\PhpOffice\PhpWord\Element\Section $section, Book $book): void
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

    private function addCopyrightPage(\PhpOffice\PhpWord\Element\Section $section, Book $book, ExportOptions $options): void
    {
        $section->addTextBreak(12);
        $text = $options->copyrightText !== '' ? $options->copyrightText : 'Copyright © '.date('Y')."\n{$book->title}\nAll rights reserved.";
        foreach (explode("\n", $text) as $line) {
            if (trim($line) !== '') {
                $section->addText(htmlspecialchars(trim($line)), ['size' => 10], ['alignment' => Jc::CENTER]);
            }
        }
        $section->addPageBreak();
    }

    private function addDedicationPage(\PhpOffice\PhpWord\Element\Section $section, ExportOptions $options): void
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

    private function addEpigraphPage(\PhpOffice\PhpWord\Element\Section $section, ExportOptions $options): void
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

    private function addChapterContent(\PhpOffice\PhpWord\Element\Section $section, mixed $chapter): void
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

    private function addBackMatter(\PhpOffice\PhpWord\Element\Section $section, Book $book, ExportOptions $options): void
    {
        foreach ($options->backMatter as $item) {
            match ($item) {
                'epilogue' => $this->addEpilogueMatter($section, $book),
                'acknowledgments' => $this->addTextMatter($section, 'Acknowledgments', $options->acknowledgmentText),
                'about-author' => $this->addTextMatter($section, 'About the Author', $options->aboutAuthorText),
                'also-by' => $this->addAlsoByMatter($section, $book, $options),
                default => null,
            };
        }
    }

    private function addEpilogueMatter(\PhpOffice\PhpWord\Element\Section $section, Book $book): void
    {
        $epilogue = ExportService::resolveEpilogueChapter($book);
        if (! $epilogue) {
            return;
        }
        $section->addPageBreak();
        $section->addText(htmlspecialchars($epilogue->title), ['bold' => true, 'size' => 16], 'ChapterTitle');
        $this->addChapterContent($section, $epilogue);
    }

    private function addTextMatter(\PhpOffice\PhpWord\Element\Section $section, string $heading, string $text): void
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

    private function addAlsoByMatter(\PhpOffice\PhpWord\Element\Section $section, Book $book, ExportOptions $options): void
    {
        if ($options->alsoByText === '') {
            return;
        }
        $section->addPageBreak();
        $section->addText(
            htmlspecialchars('Also By '.($book->author ?? '')),
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
