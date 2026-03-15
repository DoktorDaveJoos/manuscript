<?php

namespace App\Services\Parsers;

use App\Contracts\DocumentParserInterface;
use App\Services\DocxParserService;
use InvalidArgumentException;

class DocumentParserFactory
{
    /** @var list<string> */
    public const SUPPORTED_EXTENSIONS = ['docx', 'odt', 'txt', 'md', 'markdown'];

    /**
     * Resolve a parser for the given file extension.
     */
    public function forExtension(string $ext): DocumentParserInterface
    {
        return match (strtolower($ext)) {
            'docx' => new DocxParserService,
            'odt' => new OdtParserService,
            'txt' => new TxtParserService,
            'md', 'markdown' => new MarkdownParserService,
            default => throw new InvalidArgumentException("Unsupported file extension: {$ext}"),
        };
    }
}
