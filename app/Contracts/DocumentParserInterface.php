<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface DocumentParserInterface
{
    /**
     * Parse an uploaded file and extract chapters.
     *
     * @return array{chapters: list<array{number: int, title: string, word_count: int, content: string}>}
     */
    public function parse(UploadedFile $file): array;
}
