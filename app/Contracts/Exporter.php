<?php

namespace App\Contracts;

use App\Models\Book;
use App\Models\Chapter;
use App\Services\Export\ExportOptions;
use Illuminate\Support\Collection;

interface Exporter
{
    /**
     * Export a book to a file and return the file path.
     *
     * @param  Collection<int, Chapter>  $chapters
     */
    public function export(Book $book, Collection $chapters, ExportOptions $options): string;
}
