<?php

declare(strict_types=1);

namespace App\Integrations\MercadoLibre\Auth;

use App\Integrations\MercadoLibre\Client\MeliClient;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

final class OAuthConnectService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly MeliClient $client,
        private readonly TokenCipher $cipher,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUri,
    ) {
        if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
            throw new RuntimeException('Mercado Libre OAuth configuration is incomplete.');
        }
    }

    public function connectAuthorizationCode(
        int $companyId,
        string $authorizationCode,
        string $codeVerifier,
        DateTimeImmutable $issuedAt,
    ): int {
        if ($companyId < 1 || $authorizationCode === '' || $codeVerifier === '') {
            throw new RuntimeException('Mercado Libre OAuth authorization input is incomplete.');
        }

        $tokenBody = http_build_query([
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $authorizationCode,
            'redirect_uri' => $this->redirectUri,
            'code_verifier' => $codeVerifier,
        ], '', '&', PHP_QUERY_RFC3986);

        // Authorization codes are one-use. This call is intentionally dispatched once only.
        $tokenResponse = $this->client->request(
            'oauth.token',
            null,
            $tokenBody,
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            scopeKey: 'app:oauth',
            resourceCount: 1,
        );

        $accessToken = $tokenResponse->data['access_token'] ?? null;
        $refreshToken = $tokenResponse->data['refresh_token'] ?? null;
        $expiresInRaw = $tokenResponse->data['expires_in'] ?? null;

        if (!is_string($accessToken) || $accessToken === '' ||
            !is_string($refreshToken) || $refreshToken === '' ||
            !(is_int($expiresInRaw) || (is_string($expiresInRaw) && ctype_digit($expiresInRaw)))) {
            throw new RuntimeException('Mercado Libre OAuth token response is incomplete.');
        }

        $expiresIn = (int) $expiresInRaw;
        if ($expiresIn < 1) {
            throw new RuntimeException('Mercado Libre OAuth token response is incomplete.');
        }

        $identityResponse = $this->client->request(
            'users.me',
            $accessToken,
            scopeKey: 'company:' . $companyId,
            resourceCount: 1,
        );

        $externalUserIdRaw = $identityResponse->data['id'] ?? null;
        $siteId = $identityResponse->data['site_id'] ?? null;
        $nicknameRaw = $identityResponse->data['nickname'] ?? null;

        if (!(is_int($externalUserIdRaw) || (is_string($externalUserIdRaw) && ctype_digit($externalUserIdRaw))) ||
            !is_string($siteId) || $siteId === '') {
            throw new RuntimeException('Mercado Libre account identity response is incomplete.');
        }

        $externalUserId = (string) $externalUserIdRaw;
        $nickname = is_string($nicknameRaw) && $nicknameRaw !== '' ? $nicknameRaw : null;
        $expiresAt = $issuedAt
            ->setTimezone(new DateTimeZone('UTC'))
            ->modify('+' . $expiresIn . ' seconds');

        // Secrets are encrypted before the database transaction starts.
        $accessCipher = $this->cipher->encrypt($accessToken);
        $refreshCipher = $this->cipher->encrypt($refreshToken);

        $this->pdo->beginTransaction();
        try {
            $account = $this->pdo->prepare(
                'INSERT INTO meli_accounts '
                . '(company_id, external_user_id, site_id, nickname, status) '
                . "VALUES (:company_id, :external_user_id, :site_id, :nickname, 'connected') "
                . 'ON DUPLICATE KEY UPDATE '
                . 'id = LAST_INSERT_ID(id), '
                . 'site_id = VALUES(site_id), '
                . 'nickname = VALUES(nickname), '
                . "status = 'connected', "
                . 'updated_at = UTC_TIMESTAMP(6)'
            );
            $account->execute([
                'company_id' => $companyId,
                'external_user_id' => $externalUserId,
                'site_id' => $siteId,
                'nickname' => $nickname,
            ]);

            $accountId = (int) $this->pdo->lastInsertId();
            if ($accountId < 1) {
                throw new RuntimeException('Mercado Libre account could not be persisted.');
            }

            $tokens = $this->pdo->prepare(
                'INSERT INTO meli_tokens '
                . '(account_id, access_token_cipher, refresh_token_cipher, expires_at, refresh_version) '
                . 'VALUES (:account_id, :access_token_cipher, :refresh_token_cipher, :expires_at, 0) '
                . 'ON DUPLICATE KEY UPDATE '
                . 'access_token_cipher = VALUES(access_token_cipher), '
                . 'refresh_token_cipher = VALUES(refresh_token_cipher), '
                . 'expires_at = VALUES(expires_at), '
                . 'refresh_version = refresh_version + 1, '
                . 'updated_at = UTC_TIMESTAMP(6)'
            );
            $tokens->execute([
                'account_id' => $accountId,
                'access_token_cipher' => $accessCipher,
                'refresh_token_cipher' => $refreshCipher,
                'expires_at' => $expiresAt->format('Y-m-d H:i:s.u'),
            ]);

            $this->pdo->commit();
            return $accountId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
