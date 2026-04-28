<?php

namespace App\Http\Controllers;

use App\Services\BackupService;
use App\Services\InvalidPassphraseOrCiphertextException;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class BackupController extends Controller
{
    public function __construct(private BackupService $backups) {}

    /**
     * Stream the encrypted (or plain) database to the client. The browser /
     * NativePHP shell shows its native Save-As dialog. The temp file is
     * deleted after the response is sent.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $request->validate([
            'passphrase' => ['nullable', 'string', 'min:8', 'max:1024'],
        ]);

        $passphrase = (string) $request->input('passphrase', '');
        $tempPath = $this->backups->export($passphrase);

        $stamp = CarbonImmutable::now()->format('Y-m-d-Hi');
        $filename = $passphrase === ''
            ? "manuscript-{$stamp}.sqlite"
            : "manuscript-{$stamp}.msbk";

        return response()
            ->download($tempPath, $filename)
            ->deleteFileAfterSend(true)
            ->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $filename,
            );
    }

    /**
     * Validate and stage an uploaded backup file. The actual file swap
     * happens at next app boot via {@see BackupService::applyPending()}.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'backup' => ['required', 'file', 'max:512000'],
            'passphrase' => ['nullable', 'string', 'min:8', 'max:1024'],
        ]);

        $upload = $request->file('backup');
        $passphrase = (string) $request->input('passphrase', '');

        try {
            $this->backups->stageImport($upload->getRealPath(), $passphrase);
        } catch (InvalidPassphraseOrCiphertextException) {
            return response()->json([
                'message' => __('Wrong passphrase or the backup file is corrupted.'),
            ], 422);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => __('Backup ready to import. Quit Manuscript and reopen to complete the import.'),
            'requires_restart' => true,
        ]);
    }

    /**
     * Mark a revert as pending. The actual swap happens on next boot.
     */
    public function revert(): JsonResponse
    {
        try {
            $this->backups->stageRevert();
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => __('Revert ready. Quit Manuscript and reopen to restore your previous data.'),
            'requires_restart' => true,
        ]);
    }
}
