<?php

namespace App\Enums;

enum ExportFormat: string
{
    case Docx = 'docx';
    case Txt = 'txt';
    case Epub = 'epub';
    case Pdf = 'pdf';
    case Kdp = 'kdp';

    public function extension(): string
    {
        return match ($this) {
            self::Kdp => 'epub',
            default => $this->value,
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::Docx => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            self::Txt => 'text/plain',
            self::Epub, self::Kdp => 'application/epub+zip',
            self::Pdf => 'application/pdf',
        };
    }
}
