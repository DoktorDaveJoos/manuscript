<?php

use App\Services\BackupEncryptionService;
use App\Services\InvalidPassphraseOrCiphertextException;

beforeEach(function () {
    $this->service = new BackupEncryptionService;
    $this->workDir = sys_get_temp_dir().'/manuscript-backup-tests-'.uniqid();
    mkdir($this->workDir);
});

afterEach(function () {
    array_map('unlink', glob($this->workDir.'/*') ?: []);
    @rmdir($this->workDir);
});

test('encrypt then decrypt round-trips the file contents', function () {
    $src = $this->workDir.'/source.bin';
    $cipher = $this->workDir.'/source.msbk';
    $plain = $this->workDir.'/decrypted.bin';

    $payload = random_bytes(16384);
    file_put_contents($src, $payload);

    $this->service->encryptFile($src, $cipher, 'correct horse battery staple');
    $this->service->decryptFile($cipher, $plain, 'correct horse battery staple');

    expect(file_get_contents($plain))->toBe($payload);
});

test('decrypt with the wrong passphrase throws and writes nothing', function () {
    $src = $this->workDir.'/source.bin';
    $cipher = $this->workDir.'/source.msbk';
    $plain = $this->workDir.'/decrypted.bin';

    file_put_contents($src, str_repeat('x', 1024));
    $this->service->encryptFile($src, $cipher, 'right-passphrase');

    expect(fn () => $this->service->decryptFile($cipher, $plain, 'wrong-passphrase'))
        ->toThrow(InvalidPassphraseOrCiphertextException::class);

    expect(file_exists($plain))->toBeFalse();
});

test('tampered ciphertext is rejected by GCM tag', function () {
    $src = $this->workDir.'/source.bin';
    $cipher = $this->workDir.'/source.msbk';
    $plain = $this->workDir.'/decrypted.bin';

    file_put_contents($src, str_repeat('x', 1024));
    $this->service->encryptFile($src, $cipher, 'pass');

    // Flip a byte in the ciphertext region (skip past the 34-byte header).
    $blob = file_get_contents($cipher);
    $blob[40] = chr((ord($blob[40]) ^ 0x01) & 0xFF);
    file_put_contents($cipher, $blob);

    expect(fn () => $this->service->decryptFile($cipher, $plain, 'pass'))
        ->toThrow(InvalidPassphraseOrCiphertextException::class);
});

test('isEncryptedBackup detects MSBK files and ignores plain SQLite files', function () {
    $msbk = $this->workDir.'/encrypted.msbk';
    $plain = $this->workDir.'/plain.sqlite';

    file_put_contents($plain, "SQLite format 3\0".str_repeat('x', 200));

    $src = $this->workDir.'/src.bin';
    file_put_contents($src, 'hello');
    $this->service->encryptFile($src, $msbk, 'pass');

    expect($this->service->isEncryptedBackup($msbk))->toBeTrue();
    expect($this->service->isEncryptedBackup($plain))->toBeFalse();
});

test('decrypting a non-MSBK file throws', function () {
    $bogus = $this->workDir.'/bogus.bin';
    $out = $this->workDir.'/out.bin';
    file_put_contents($bogus, str_repeat("\0", 100));

    expect(fn () => $this->service->decryptFile($bogus, $out, 'pass'))
        ->toThrow(InvalidPassphraseOrCiphertextException::class);
});
