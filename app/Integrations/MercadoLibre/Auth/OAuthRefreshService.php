<?php

declare(strict_types=1);

namespace App\Integrations\MercadoLibre\Auth;

use App\Integrations\MercadoLibre\Client\MeliApiException;
use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Client\MeliRateLimitException;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

final class OAuthRefreshService
{
    private const VALIDITY_MARGIN_SECONDS = 60;

    public function __construct(
        private readonly PDO $pdo,
        private readonly PDO $lockConnection,
        private readonly MeliClient $client,
        private readonly TokenCipher $cipher,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $lockPrefix,
    ) {
        if ($clientId === '' || $clientSecret === '' || $lockPrefix === '') {
            throw new RuntimeException('Mercado Libre OAuth refresh configuration is incomplete.');
        }
    }

    public function getValidAccessToken(int $accountId, DateTimeImmutable $now): string
    {
        if ($accountId < 1) {
            throw new RuntimeException('Mercado Libre account is invalid.');
        }

        $nowUtc = $now->setTimezone(new DateTimeZone('UTC'));
        $current = $this->loadToken($accountId);
        if ($this->isSufficientlyValid($current['expires_at'], $nowUtc)) {
            return $this->cipher->decrypt($current['access_token_cipher']);
        }

        $lockName = $this->lockName($accountId);
        if (!$this->acquireLock($lockName)) {
            throw new RuntimeException('Mercado Libre OAuth refresh is already in progress.');
        }

        try {
            // Refresh tokens are single-use. Always reread after taking the per-account lock.
            $current = $this->loadToken($accountId);
            if ($this->isSufficientlyValid($current['expires_at'], $nowUtc)) {
                return $this->cipher->decrypt($current['access_token_cipher']);
            }

            $refreshToken = $this->cipher->decrypt($current['refresh_token_cipher']);
            $body = http_build_query([
                'grant_type' => 'refresh_token',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $refreshToken,
            ], '', '&', PHP_QUERY_RFC3986);

            try {
                // A refresh token is one-use. This POST is intentionally dispatched once only.
                $response = $this->client->request(
                    'oauth.token',
                    null,
                    $body,
                    ['Content-Type' => 'application/x-www-form-urlencoded'],
                    scopeKey: 'account:' . $accountId,
                    resourceCount: 1,
                );
            } catch (MeliApiException $exception) {
                if ($exception->errorCode === 'invalid_grant') {
                    $this->setAccountStatus($accountId, 'reauth_required');
                    throw new RuntimeException('Mercado Libre authorization must be renewed.', 0, $exception);
                }

                throw $exception;
            } catch (MeliRateLimitException $exception) {
                // A definite 429 response is handled by the caller/cooldown policy; do not consume another POST here.
                throw $exception;
            } catch (RuntimeException $exception) {
                // Network/transport or malformed-response outcomes can be ambiguous after a one-use refresh POST.
                $this->setAccountStatus($accountId, 'attention');
                throw new RuntimeException(
                    'Mercado Libre OAuth refresh outcome is uncertain; reauthorization may be required.',
                    0,
                    $exception,
                );
            }

            $accessToken = $response->data['access_token'] ?? null;
            $newRefreshToken = $response->data['refresh_token'] ?? null;
            $expiresInRaw = $response->data['expires_in'] ?? null;

            if (!is_string($accessToken) || $accessToken === '' ||
                !is_string($newRefreshToken) || $newRefreshToken === '' ||
                !(is_int($expiresInRaw) || (is_string($expiresInRaw) && ctype_digit($expiresInRaw)))) {
                $this->setAccountStatus($accountId, 'attention');
                throw new RuntimeException('Mercado Libre OAuth refresh response is incomplete.');
            }

            $expiresIn = (int) $expiresInRaw;
            if ($expiresIn < 1) {
                $this->setAccountStatus($accountId, 'attention');
                throw new RuntimeException('Mercado Libre OAuth refresh response is incomplete.');
            }

            $accessCipher = $this->cipher->encrypt($accessToken);
            $refreshCipher = $this->cipher->encrypt($newRefreshToken);
            $expiresAt = $nowUtc->modify('+' . $expiresIn . ' seconds');

            $this->pdo->beginTransaction();
            try {
                $tokens = $this->pdo->prepare(
                    'UPDATE meli_tokens SET '
                    . 'access_token_cipher = :access_token_cipher, '
                    . 'refresh_token_cipher = :refresh_token_cipher, '
                    . 'expires_at = :expires_at, '
                    . 'refresh_version = refresh_version + 1, '
                    . 'updated_at = UTC_TIMESTAMP(6) '
                    . 'WHERE account_id = :account_id'
                );
                $tokens->execute([
                    'access_token_cipher' => $accessCipher,
                    'refresh_token_cipher' => $refreshCipher,
                    'expires_at' => $expiresAt->format('Y-m-d H:i:s.u'),
                    'account_id' => $accountId,
                ]);

                if ($tokens->rowCount() !== 1) {
                    throw new RuntimeException('Mercado Libre OAuth tokens could not be persisted.');
                }

                $account = $this->pdo->prepare(
                    "UPDATE meli_accounts SET status = 'connected', updated_at = UTC_TIMESTAMP(6) WHERE id = :id"
                );
                $account->execute(['id' => $accountId]);

                if ($account->rowCount() > 1) {
                    throw new RuntimeException('Unexpected Mercado Libre account update count.');
                }

                $this->pdo->commit();
            } catch (Throwable $exception) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $exception;
            }

            return $accessToken;
        } finally {
            $this->releaseLock($lockName);
        }
    }

    /**
     * @return array{access_token_cipher:string,refresh_token_cipher:string,expires_at:string}
     */
    private function loadToken(int $accountId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT t.access_token_cipher, t.refresh_token_cipher, t.expires_at '
            . 'FROM meli_tokens t '
            . 'INNER JOIN meli_accounts a ON a.id = t.account_id '
            . 'WHERE t.account_id = :account_id AND a.status <> \'disabled\''
        );
        $statement->execute(['account_id' => $accountId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            throw new RuntimeException('Mercado Libre account token is unavailable.');
        }

        return [
            'access_token_cipher' => (string) $row['access_token_cipher'],
            'refresh_token_cipher' => (string) $row['refresh_token_cipher'],
            'expires_at' => (string) $row['expires_at'],
        ];
    }

    private function isSufficientlyValid(string $expiresAt, DateTimeImmutable $nowUtc): bool
    {
        $expiry = new DateTimeImmutable($expiresAt, new DateTimeZone('UTC'));
        return $expiry->getTimestamp() > $nowUtc->getTimestamp() + self::VALIDITY_MARGIN_SECONDS;
    }

    private function lockName(int $accountId): string
    {
        $database = (string) $this->lockConnection->query('SELECT DATABASE()')->fetchColumn();
        if ($database === '') {
            throw new RuntimeException('Mercado Libre OAuth refresh database is unavailable.');
        }

        return $database . '.' . $this->lockPrefix . '.' . $accountId;
    }

    private function acquireLock(string $lockName): bool
    {
        $statement = $this->lockConnection->prepare('SELECT GET_LOCK(:lock_name, 0)');
        $statement->execute(['lock_name' => $lockName]);
        return (int) $statement->fetchColumn() === 1;
    }

    private function releaseLock(string $lockName): void
    {
        $statement = $this->lockConnection->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $statement->execute(['lock_name' => $lockName]);
    }

    private function setAccountStatus(int $accountId, string $status): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE meli_accounts SET status = :status, updated_at = UTC_TIMESTAMP(6) WHERE id = :id'
        );
        $statement->execute([
            'status' => $status,
            'id' => $accountId,
        ]);
    }
}
