<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Integrations\MercadoLibre\Auth\TokenCipher;
use RuntimeException;
use PHPUnit\Framework\TestCase;

final class TokenCipherTest extends TestCase
{
    public function testEncryptDecryptRoundTripUsesRandomNonce(): void
    {
        $cipher = new TokenCipher('test-application-secret');

        $first = $cipher->encrypt('APP_USR-secret-token');
        $second = $cipher->encrypt('APP_USR-secret-token');

        self::assertNotSame('APP_USR-secret-token', $first);
        self::assertNotSame($first, $second);
        self::assertSame('APP_USR-secret-token', $cipher->decrypt($first));
        self::assertSame('APP_USR-secret-token', $cipher->decrypt($second));
    }

    public function testWrongApplicationSecretCannotDecryptAndDoesNotLeakPlaintext(): void
    {
        $encrypted = (new TokenCipher('correct-secret'))->encrypt('TG-sensitive-refresh-token');

        try {
            (new TokenCipher('wrong-secret'))->decrypt($encrypted);
            self::fail('Decrypting with the wrong application secret must fail.');
        } catch (RuntimeException $exception) {
            self::assertStringNotContainsString('TG-sensitive-refresh-token', $exception->getMessage());
            self::assertSame('Unable to decrypt protected value.', $exception->getMessage());
        }
    }
}
