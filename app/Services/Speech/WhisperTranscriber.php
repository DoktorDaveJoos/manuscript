<?php

namespace App\Services\Speech;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Runs the bundled whisper.cpp CLI against a 16 kHz mono WAV file. The binary
 * ships inside the app via NativePHP's extras/ mechanism; if it is absent
 * (dev checkout without binaries, or a packaging regression) the feature
 * degrades to unavailable instead of erroring.
 */
class WhisperTranscriber
{
    public function __construct(
        private readonly SpeechModelManager $model,
        private readonly ?string $osFamily = null,
    ) {}

    public function binaryPath(): string
    {
        $osFamily = $this->osFamily ?? PHP_OS_FAMILY;

        $relative = $osFamily === 'Windows'
            ? 'bin'.DIRECTORY_SEPARATOR.'win'.DIRECTORY_SEPARATOR.'whisper-cli.exe'
            : 'bin'.DIRECTORY_SEPARATOR.'mac'.DIRECTORY_SEPARATOR.'whisper-cli';

        return config('speech.extras_dir').DIRECTORY_SEPARATOR.$relative;
    }

    public function isAvailable(): bool
    {
        return File::exists($this->binaryPath());
    }

    public function transcribe(string $audioPath): string
    {
        $result = Process::timeout((int) config('speech.transcribe_timeout'))
            ->run([
                $this->binaryPath(),
                '-m', $this->model->path(),
                '-f', $audioPath,
                '-l', 'auto',
                '-np',
                '-nt',
            ])
            ->throw();

        return trim($result->output());
    }
}
