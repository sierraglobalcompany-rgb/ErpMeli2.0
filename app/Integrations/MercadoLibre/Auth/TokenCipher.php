<?php

declare(strict_types=1);

namespace App\Integrations\MercadoLibre\Auth;

use RuntimeException;
use SodiumException;

final class TokenCipher
{
    private const PREFIX = 'v1:';

    private readonly string $key;

    public function __construct(string $applicationSecret)
    {
        if ($applicationSecret === '') {
            throw new RuntimeException('Application secret is required for token protection.');
        }

        $this->key = sodium_crypto_generichash(
            'erp-meli2:meli-token|' . $applicationSecret,
            '',
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
        );
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key);

        return self::PREFIX . sodium_bin2base64(
            $nonce . $ciphertext,
            SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING,
        );
    }

    public function decrypt(string $protectedValue): string
    {
        try {
            if (!str_starts_with($protectedValue, self::PREFIX)) {
                throw new RuntimeException('Invalid protected value version.');
            }

            $encoded = substr($protectedValue, strlen(self::PREFIX));
            $payload = sodium_base642bin($encoded, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);

            if (strlen($payload) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
                throw new RuntimeException('Invalid protected value payload.');
            }

            $nonce = substr($payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ciphertext = substr($payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);

            if ($plaintext === false) {
                throw new RuntimeException('Unable to decrypt protected value.');
            }

            return $plaintext;
        } catch (SodiumException | RuntimeException $exception) {
            throw new RuntimeException('Unable to decrypt protected value.', 0, $exception);
        }
    }
}
