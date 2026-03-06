<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Chapter;
use Illuminate\Support\Collection;
use PhpOffice\PhpWord\PhpWord;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportService
{
    /**
     * Export a book as a downloadable file.
     *
     * @param  array<string, mixed>  $options
     */
    public function export(Book $book, array $options): BinaryFileResponse
    {
        $chapters = $this->resolveChapters($book, $options);
        $includeChapterTitles = (bool) ($options['include_chapter_titles'] ?? true);
        $includeActBreaks = (bool) ($options['include_act_breaks'] ?? false);

        $format = $options['format'] ?? 'docx';

        if ($format === 'txt') {
            return $this->exportTxt($book, $chapters, $includeChapterTitles, $includeActBreaks);
        }

        return $this->exportDocx($book, $chapters, $includeChapterTitles, $includeActBreaks);
    }

    /**
     * @return Collection<int, Chapter>
     */
    private function resolveChapters(Book $book, array $options): Collection
    {
        $query = $book->chapters()
            ->with(['versions' => fn ($q) => $q->where('is_current', true), 'act', 'storyline', 'scenes' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('reader_order');

        $scope = $options['scope'] ?? 'full';

        if ($scope === 'chapter' && isset($options['chapter_id'])) {
            $query->where('id', $options['chapter_id']);
        } elseif ($scope === 'storyline' && isset($options['storyline_id'])) {
            $query->where('storyline_id', $options['storyline_id']);
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, Chapter>  $chapters
     */
    private function exportDocx(Book $book, Collection $chapters, bool $includeChapterTitles, bool $includeActBreaks): BinaryFileResponse
    {
        $word = new PhpWord;
        $section = $word->addSection();

        $currentActId = null;

        foreach ($chapters as $chapter) {
            if ($includeActBreaks && $chapter->act_id && $chapter->act_id !== $currentActId) {
                $currentActId = $chapter->act_id;
                $section->addText(
                    $chapter->act?->title ?? "Act {$chapter->act?->number}",
                    ['size' => 18, 'bold' => true],
                    ['spaceBefore' => 400, 'spaceAfter' => 200],
                );
            }

            if ($includeChapterTitles) {
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
                $plainText = strip_tags(str_replace(['<p>', '</p>', '<br>', '<br/>', '<br />', '<hr>', '<hr/>', '<hr />'], ["\n", "\n", "\n", "\n", "\n", "\n---\n", "\n---\n", "\n---\n"], $content));

                foreach (explode("\n", $plainText) as $paragraph) {
                    $trimmed = trim($paragraph);
                    if ($trimmed === '') {
                        continue;
                    }
                    if ($trimmed === '---') {
                        $section->addText('* * *', ['italic' => true], ['alignment' => 'center', 'spaceBefore' => 200, 'spaceAfter' => 200]);
                    } else {
                        $section->addText($trimmed, ['size' => 12], ['spaceAfter' => 100]);
                    }
                }
            }
        }

        $filename = storage_path('app/'.str_replace(' ', '_', $book->title).'.docx');
        $word->save($filename);

        return response()->download($filename, $book->title.'.docx')->deleteFileAfterSend();
    }

    /**
     * @param  Collection<int, Chapter>  $chapters
     */
    private function exportTxt(Book $book, Collection $chapters, bool $includeChapterTitles, bool $includeActBreaks): BinaryFileResponse
    {
        $lines = [];
        $currentActId = null;

        foreach ($chapters as $chapter) {
            if ($includeActBreaks && $chapter->act_id && $chapter->act_id !== $currentActId) {
                $currentActId = $chapter->act_id;
                $lines[] = '';
                $lines[] = strtoupper($chapter->act?->title ?? "ACT {$chapter->act?->number}");
                $lines[] = str_repeat('=', 40);
                $lines[] = '';
            }

            if ($includeChapterTitles) {
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
                $plainText = strip_tags(str_replace(['<p>', '</p>', '<br>', '<br/>', '<br />', '<hr>', '<hr/>', '<hr />'], ["\n", "\n", "\n", "\n", "\n", "\n* * *\n", "\n* * *\n", "\n* * *\n"], $content));
                $lines[] = trim($plainText);
            }
            $lines[] = '';
        }

        $filename = storage_path('app/'.str_replace(' ', '_', $book->title).'.txt');
        file_put_contents($filename, implode("\n", $lines));

        return response()->download($filename, $book->title.'.txt')->deleteFileAfterSend();
    }
}
