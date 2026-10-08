<?php

declare(strict_types=1);

namespace App\Integrations\MercadoLibre\Auth;

use DateTimeImmutable;
use RuntimeException;

final class OAuthAuthorizationFlow
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $redirectUri,
        private readonly string $authorizationUrl,
        private readonly int $stateTtlSeconds = 600,
    ) {
        if ($clientId === '' || $redirectUri === '' || $authorizationUrl === '') {
            throw new RuntimeException('Mercado Libre OAuth configuration is incomplete.');
        }
        if ($stateTtlSeconds < 1) {
            throw new RuntimeException('Mercado Libre OAuth state TTL must be positive.');
        }
    }

    /**
     * @param array<string,mixed> $session
     * @return array{url:string,state:string}
     */
    public function begin(array &$session, DateTimeImmutable $now): array
    {
        $state = $this->base64Url(random_bytes(32));
        $codeVerifier = $this->base64Url(random_bytes(32));
        $codeChallenge = $this->base64Url(hash('sha256', $codeVerifier, true));

        $session['meli_oauth'] = [
            'state' => $state,
            'code_verifier' => $codeVerifier,
            'issued_at' => $now->getTimestamp(),
        ];

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);

        return [
            'url' => rtrim($this->authorizationUrl, '?') . '?' . $query,
            'state' => $state,
        ];
    }

    /**
     * @param array<string,mixed> $session
     */
    public function consume(array &$session, string $returnedState, DateTimeImmutable $now): string
    {
        $pending = $session['meli_oauth'] ?? null;
        unset($session['meli_oauth']);

        if (!is_array($pending)) {
            throw new RuntimeException('Mercado Libre OAuth authorization state is missing or expired.');
        }

        $expectedState = $pending['state'] ?? null;
        $codeVerifier = $pending['code_verifier'] ?? null;
        $issuedAt = $pending['issued_at'] ?? null;

        if (!is_string($expectedState) || $expectedState === '' ||
            !is_string($codeVerifier) || $codeVerifier === '' ||
            !is_int($issuedAt)) {
            throw new RuntimeException('Mercado Libre OAuth authorization state is missing or expired.');
        }

        $age = $now->getTimestamp() - $issuedAt;
        if ($age < 0 || $age > $this->stateTtlSeconds) {
            throw new RuntimeException('Mercado Libre OAuth authorization state is missing or expired.');
        }

        if ($returnedState === '' || !hash_equals($expectedState, $returnedState)) {
            throw new RuntimeException('Mercado Libre OAuth authorization state is invalid.');
        }

        return $codeVerifier;
    }

    private function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
