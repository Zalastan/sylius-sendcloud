<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\SendCloudPlugin\Encryption;

final class CredentialEncryptor
{
    private readonly string $key;

    public function __construct(string $appSecret)
    {
        // Derive a stable 32-byte key from APP_SECRET using BLAKE2b
        $this->key = sodium_crypto_generichash($appSecret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key);

        return base64_encode($nonce . $ciphertext);
    }

    public function decrypt(string $encrypted): string
    {
        $decoded = base64_decode($encrypted, strict: true);

        if ($decoded === false || \strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Invalid encrypted credential data.');
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);

        if ($plaintext === false) {
            throw new \RuntimeException('Credential decryption failed. APP_SECRET may have changed.');
        }

        return $plaintext;
    }

    public function isEncrypted(string $value): bool
    {
        $decoded = base64_decode($value, strict: true);
        $minLength = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES + 1;

        return $decoded !== false && \strlen($decoded) >= $minLength;
    }
}
