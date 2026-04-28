<?php

namespace App\Services;

use RuntimeException;

/**
 * Encrypt and decrypt backup files using a user-supplied passphrase.
 *
 * File envelope ("MSBK", v1):
 *   - magic         4 bytes   "MSBK"
 *   - version       1 byte    0x01
 *   - kdf id        1 byte    0x01 = PBKDF2-SHA256, 600_000 iters
 *   - salt         16 bytes
 *   - nonce        12 bytes
 *   - ciphertext   N bytes    AES-256-GCM(plaintext); AAD = the 34-byte header
 *   - tag          16 bytes   GCM auth tag
 *
 * The passphrase is never stored. The GCM tag rejects tampered files and
 * wrong passphrases identically — both cases raise {@see InvalidPassphraseOrCiphertextException}.
 */
class BackupEncryptionService
{
    public const MAGIC = 'MSBK';

    public const VERSION = 0x01;

    public const KDF_PBKDF2_SHA256 = 0x01;

    public const PBKDF2_ITERATIONS = 600_000;

    public const SALT_BYTES = 16;

    public const NONCE_BYTES = 12;

    public const KEY_BYTES = 32;

    public const TAG_BYTES = 16;

    public const HEADER_BYTES = 4 + 1 + 1 + self::SALT_BYTES + self::NONCE_BYTES;

    /**
     * Encrypt $sourcePath into $destPath using $passphrase.
     *
     * Reads the source into memory; SQLite database files for this app are
     * orders of magnitude smaller than the 1G PHP memory limit, so streaming
     * is unnecessary complexity for v1.
     */
    public function encryptFile(string $sourcePath, string $destPath, string $passphrase): void
    {
        if ($passphrase === '') {
            throw new RuntimeException('Passphrase must not be empty.');
        }

        $plaintext = @file_get_contents($sourcePath);
        if ($plaintext === false) {
            throw new RuntimeException("Could not read source file: {$sourcePath}");
        }

        $salt = random_bytes(self::SALT_BYTES);
        $nonce = random_bytes(self::NONCE_BYTES);
        $key = $this->deriveKey($passphrase, $salt);

        $header = self::MAGIC
            .chr(self::VERSION)
            .chr(self::KDF_PBKDF2_SHA256)
            .$salt
            .$nonce;

        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $header,
            self::TAG_BYTES,
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed.');
        }

        $written = @file_put_contents($destPath, $header.$ciphertext.$tag);
        if ($written === false) {
            throw new RuntimeException("Could not write destination file: {$destPath}");
        }
    }

    /**
     * Decrypt $sourcePath into $destPath using $passphrase. Throws
     * {@see InvalidPassphraseOrCiphertextException} on wrong passphrase or
     * tampered ciphertext (the GCM tag does not distinguish these — that is
     * by design).
     */
    public function decryptFile(string $sourcePath, string $destPath, string $passphrase): void
    {
        if ($passphrase === '') {
            throw new RuntimeException('Passphrase must not be empty.');
        }

        $blob = @file_get_contents($sourcePath);
        if ($blob === false) {
            throw new RuntimeException("Could not read source file: {$sourcePath}");
        }

        if (strlen($blob) < self::HEADER_BYTES + self::TAG_BYTES) {
            throw new InvalidPassphraseOrCiphertextException;
        }

        $magic = substr($blob, 0, 4);
        if ($magic !== self::MAGIC) {
            throw new InvalidPassphraseOrCiphertextException;
        }

        $version = ord($blob[4]);
        if ($version !== self::VERSION) {
            throw new RuntimeException("Unsupported backup version: {$version}");
        }

        $kdfId = ord($blob[5]);
        if ($kdfId !== self::KDF_PBKDF2_SHA256) {
            throw new RuntimeException("Unsupported KDF id: {$kdfId}");
        }

        $salt = substr($blob, 6, self::SALT_BYTES);
        $nonce = substr($blob, 6 + self::SALT_BYTES, self::NONCE_BYTES);
        $header = substr($blob, 0, self::HEADER_BYTES);

        $tag = substr($blob, -self::TAG_BYTES);
        $ciphertext = substr($blob, self::HEADER_BYTES, -self::TAG_BYTES);

        $key = $this->deriveKey($passphrase, $salt);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $header,
        );

        if ($plaintext === false) {
            throw new InvalidPassphraseOrCiphertextException;
        }

        $written = @file_put_contents($destPath, $plaintext);
        if ($written === false) {
            throw new RuntimeException("Could not write destination file: {$destPath}");
        }
    }

    /**
     * True iff the file at $path begins with the MSBK magic and a supported
     * version byte. Used by the import flow to decide whether to prompt for
     * a passphrase. A `false` return means "treat as a plain SQLite file".
     */
    public function isEncryptedBackup(string $path): bool
    {
        $fp = @fopen($path, 'rb');
        if ($fp === false) {
            return false;
        }

        try {
            $header = fread($fp, 5);
        } finally {
            fclose($fp);
        }

        if ($header === false || strlen($header) < 5) {
            return false;
        }

        return substr($header, 0, 4) === self::MAGIC && ord($header[4]) === self::VERSION;
    }

    private function deriveKey(string $passphrase, string $salt): string
    {
        return hash_pbkdf2(
            'sha256',
            $passphrase,
            $salt,
            self::PBKDF2_ITERATIONS,
            self::KEY_BYTES,
            true,
        );
    }
}
