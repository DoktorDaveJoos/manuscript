<?php

namespace App\Services\Export\Exporters;

use App\Contracts\Exporter;
use App\Models\Book;
use App\Services\Export\ContentPreparer;
use App\Services\Export\ExportOptions;
use App\Services\Export\ExportService;
use Illuminate\Support\Collection;
use PhpOffice\PhpWord\PhpWord;

class DocxExporter implements Exporter
{
    public function __construct(
        private ContentPreparer $contentPreparer,
    ) {}

    public function export(Book $book, Collection $chapters, ExportOptions $options): string
    {
        $word = new PhpWord;
        $section = $word->addSection();

        $currentActId = null;

        foreach ($chapters as $chapter) {
            if ($options->includeActBreaks && $chapter->act_id && $chapter->act_id !== $currentActId) {
                $currentActId = $chapter->act_id;
                $section->addText(
                    $chapter->act?->title ?? "Act {$chapter->act?->number}",
                    ['size' => 18, 'bold' => true],
                    ['spaceBefore' => 400, 'spaceAfter' => 200],
                );
            }

            if ($options->includeChapterTitles) {
                $section->addText(
                    $chapter->title,
                    ['size' => 16, 'bold' => true],
                    ['spaceBefore' => 300, 'spaceAfter' => 150],
                );
            }

            $scenes = $chapter->scenes ?? collect();
            foreach ($scenes as $sceneIndex => $scene) {
                if ($sceneIndex > 0) {
                    $section->addText('* * *', ['italic' => true], ['alignment' => 'center', 'spaceBefore' => 200, 'spaceAfter' => 200]);
                }

                $content = $scene->content ?? '';
                $plainText = $this->contentPreparer->toPlainText($content);

                foreach (explode("\n", $plainText) as $paragraph) {
                    $trimmed = trim($paragraph);
                    if ($trimmed === '') {
                        continue;
                    }
                    if ($trimmed === '* * *') {
                        $section->addText('* * *', ['italic' => true], ['alignment' => 'center', 'spaceBefore' => 200, 'spaceAfter' => 200]);
                    } else {
                        $section->addText($trimmed, ['size' => 12], ['spaceAfter' => 100]);
                    }
                }
            }
        }

        $filename = ExportService::tempPath('docx');
        $word->save($filename);

        return $filename;
    }
}
