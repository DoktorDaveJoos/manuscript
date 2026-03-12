<?php

namespace App\Services\Export\Exporters;

use App\Contracts\Exporter;
use App\Models\Book;
use App\Services\Export\ContentPreparer;
use App\Services\Export\ExportOptions;
use App\Services\Export\ExportService;
use Illuminate\Support\Collection;

class TxtExporter implements Exporter
{
    public function __construct(
        private ContentPreparer $contentPreparer,
    ) {}

    public function export(Book $book, Collection $chapters, ExportOptions $options): string
    {
        $lines = [];
        $currentActId = null;

        foreach ($chapters as $chapter) {
            if ($options->includeActBreaks && $chapter->act_id && $chapter->act_id !== $currentActId) {
                $currentActId = $chapter->act_id;
                $lines[] = '';
                $lines[] = strtoupper($chapter->act?->title ?? "ACT {$chapter->act?->number}");
                $lines[] = str_repeat('=', 40);
                $lines[] = '';
            }

            if ($options->includeChapterTitles) {
                $lines[] = '';
                $lines[] = $chapter->title;
                $lines[] = str_repeat('-', strlen($chapter->title));
                $lines[] = '';
            }

            $scenes = $chapter->scenes ?? collect();
            foreach ($scenes as $sceneIndex => $scene) {
                if ($sceneIndex > 0) {
                    $lines[] = '';
                    $lines[] = '* * *';
                    $lines[] = '';
                }

                $content = $scene->content ?? '';
                $lines[] = trim($this->contentPreparer->toPlainText($content));
            }
            $lines[] = '';
        }

        $filename = ExportService::tempPath('txt');
        file_put_contents($filename, implode("\n", $lines));

        return $filename;
    }
}
