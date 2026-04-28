<?php

namespace App\Services;

use RuntimeException;

/**
 * Thrown when a backup file fails to decrypt — either because the passphrase
 * is wrong or because the ciphertext has been tampered with. The two cases
 * are deliberately indistinguishable: the GCM tag check returns the same
 * failure regardless of cause, and surfacing finer-grained reasons would
 * leak information about the contents.
 */
class InvalidPassphraseOrCiphertextException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Invalid passphrase or corrupted backup file.');
    }
}
