<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateLicenseCommand extends Command
{
    protected $signature = 'license:generate {--keypair : Generate a new Ed25519 keypair} {--count=1 : Number of license keys to generate}';

    protected $description = 'Generate Ed25519 license keys or keypairs';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('This command can only be run in the local environment.');

            return self::FAILURE;
        }

        if ($this->option('keypair')) {
            return $this->generateKeypair();
        }

        return $this->generateKeys();
    }

    private function generateKeypair(): int
    {
        if (! function_exists('sodium_crypto_sign_keypair')) {
            $this->error('The sodium PHP extension is required but not loaded.');

            return self::FAILURE;
        }

        $keypair = sodium_crypto_sign_keypair();
        $secretKey = base64_encode(sodium_crypto_sign_secretkey($keypair));
        $publicKey = base64_encode(sodium_crypto_sign_publickey($keypair));

        $this->info('Ed25519 keypair generated:');
        $this->newLine();
        $this->line('SECRET KEY (add to .env as LICENSE_SIGNING_KEY — never ship this):');
        $this->line($secretKey);
        $this->newLine();
        $this->line('PUBLIC KEY (add to config/app.php as license_public_key default):');
        $this->line($publicKey);

        return self::SUCCESS;
    }

    private function generateKeys(): int
    {
        if (! function_exists('sodium_crypto_sign_keypair')) {
            $this->error('The sodium PHP extension is required but not loaded.');

            return self::FAILURE;
        }

        $secretKeyB64 = env('LICENSE_SIGNING_KEY');

        if (! $secretKeyB64) {
            $this->error('LICENSE_SIGNING_KEY is not set in .env. Run with --keypair to generate one.');

            return self::FAILURE;
        }

        $secretKey = base64_decode($secretKeyB64, strict: true);

        if ($secretKey === false || strlen($secretKey) !== \SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            $this->error('LICENSE_SIGNING_KEY is invalid. It must be a base64-encoded Ed25519 secret key.');

            return self::FAILURE;
        }

        $count = max(1, (int) $this->option('count'));

        for ($i = 0; $i < $count; $i++) {
            $id = strtoupper(bin2hex(random_bytes(4)));
            $signature = sodium_crypto_sign_detached($id, $secretKey);
            $signatureB64 = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

            $this->line('MANU.'.$id.'.'.$signatureB64);
        }

        return self::SUCCESS;
    }
}
