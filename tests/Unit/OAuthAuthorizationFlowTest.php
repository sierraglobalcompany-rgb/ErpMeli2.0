<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Integrations\MercadoLibre\Auth\OAuthAuthorizationFlow;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OAuthAuthorizationFlowTest extends TestCase
{
    private const CLIENT_ID = '123456789';
    private const REDIRECT_URI = 'https://erpmeli.bodegadigitalmedellin.com/oauth/mercadolibre/callback';
    private const AUTH_URL = 'https://auth.mercadolibre.com.co/authorization';

    public function testBeginCreatesStateAndPkceS256ForExactRedirectUri(): void
    {
        $session = [];
        $flow = new OAuthAuthorizationFlow(
            self::CLIENT_ID,
            self::REDIRECT_URI,
            self::AUTH_URL,
            600,
        );
        $now = new DateTimeImmutable('2026-10-08T18:00:00+00:00');

        $request = $flow->begin($session, $now);

        self::assertSame($request['state'], $session['meli_oauth']['state']);
        self::assertSame($now->getTimestamp(), $session['meli_oauth']['issued_at']);
        self::assertArrayHasKey('code_verifier', $session['meli_oauth']);
        self::assertGreaterThanOrEqual(43, strlen((string) $session['meli_oauth']['code_verifier']));
        self::assertLessThanOrEqual(128, strlen((string) $session['meli_oauth']['code_verifier']));

        $parts = parse_url($request['url']);
        self::assertIsArray($parts);
        self::assertSame('https', $parts['scheme'] ?? null);
        self::assertSame('auth.mercadolibre.com.co', $parts['host'] ?? null);
        self::assertSame('/authorization', $parts['path'] ?? null);

        parse_str((string) ($parts['query'] ?? ''), $query);
        self::assertSame('code', $query['response_type'] ?? null);
        self::assertSame(self::CLIENT_ID, $query['client_id'] ?? null);
        self::assertSame(self::REDIRECT_URI, $query['redirect_uri'] ?? null);
        self::assertSame($request['state'], $query['state'] ?? null);
        self::assertSame('S256', $query['code_challenge_method'] ?? null);

        $expectedChallenge = rtrim(strtr(
            base64_encode(hash('sha256', (string) $session['meli_oauth']['code_verifier'], true)),
            '+/',
            '-_',
        ), '=');
        self::assertSame($expectedChallenge, $query['code_challenge'] ?? null);
    }

    public function testStateAndVerifierAreRandomPerAuthorizationAttempt(): void
    {
        $flow = new OAuthAuthorizationFlow(self::CLIENT_ID, self::REDIRECT_URI, self::AUTH_URL, 600);
        $now = new DateTimeImmutable('2026-10-08T18:00:00+00:00');
        $firstSession = [];
        $secondSession = [];

        $first = $flow->begin($firstSession, $now);
        $second = $flow->begin($secondSession, $now);

        self::assertNotSame($first['state'], $second['state']);
        self::assertNotSame(
            $firstSession['meli_oauth']['code_verifier'],
            $secondSession['meli_oauth']['code_verifier'],
        );
    }

    public function testConsumeValidatesStateReturnsVerifierAndIsSingleUse(): void
    {
        $session = [];
        $flow = new OAuthAuthorizationFlow(self::CLIENT_ID, self::REDIRECT_URI, self::AUTH_URL, 600);
        $issuedAt = new DateTimeImmutable('2026-10-08T18:00:00+00:00');
        $request = $flow->begin($session, $issuedAt);
        $expectedVerifier = (string) $session['meli_oauth']['code_verifier'];

        $verifier = $flow->consume(
            $session,
            $request['state'],
            $issuedAt->modify('+5 minutes'),
        );

        self::assertSame($expectedVerifier, $verifier);
        self::assertArrayNotHasKey('meli_oauth', $session);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mercado Libre OAuth authorization state is missing or expired.');
        $flow->consume($session, $request['state'], $issuedAt->modify('+5 minutes'));
    }

    public function testStateMismatchIsRejectedAndPendingStateIsConsumed(): void
    {
        $session = [];
        $flow = new OAuthAuthorizationFlow(self::CLIENT_ID, self::REDIRECT_URI, self::AUTH_URL, 600);
        $issuedAt = new DateTimeImmutable('2026-10-08T18:00:00+00:00');
        $flow->begin($session, $issuedAt);

        try {
            $flow->consume($session, 'wrong-state', $issuedAt->modify('+1 minute'));
            self::fail('OAuth state mismatch must be rejected.');
        } catch (RuntimeException $exception) {
            self::assertSame('Mercado Libre OAuth authorization state is invalid.', $exception->getMessage());
        }

        self::assertArrayNotHasKey('meli_oauth', $session, 'A failed callback must not leave replayable state behind.');
    }

    public function testExpiredStateIsRejectedAndConsumed(): void
    {
        $session = [];
        $flow = new OAuthAuthorizationFlow(self::CLIENT_ID, self::REDIRECT_URI, self::AUTH_URL, 600);
        $issuedAt = new DateTimeImmutable('2026-10-08T18:00:00+00:00');
        $request = $flow->begin($session, $issuedAt);

        try {
            $flow->consume($session, $request['state'], $issuedAt->modify('+601 seconds'));
            self::fail('Expired OAuth state must be rejected.');
        } catch (RuntimeException $exception) {
            self::assertSame('Mercado Libre OAuth authorization state is missing or expired.', $exception->getMessage());
        }

        self::assertArrayNotHasKey('meli_oauth', $session);
    }
}
