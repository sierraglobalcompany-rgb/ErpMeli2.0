<?php

declare(strict_types=1);

namespace App\Modules\Billing\C0;

use App\Integrations\MercadoLibre\Auth\TokenCipher;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

final class BillingC0Runtime
{
    private const VALIDITY_MARGIN_SECONDS = 60;

    public function __construct(
        private readonly PDO $pdo,
        private readonly TokenCipher $cipher,
    ) {
    }

    /**
     * @return array{account_id:int,company_id:int,site_id:string,access_token:string,expires_at:string}
     */
    public function resolve(int $accountId, DateTimeImmutable $now): array
    {
        if ($accountId < 1) {
            throw new RuntimeException('Billing C0 Mercado Libre account/token is unavailable.');
        }

        $statement = $this->pdo->prepare(
            'SELECT a.id, a.company_id, a.site_id, a.status, t.access_token_cipher, t.expires_at '
            . 'FROM meli_accounts a '
            . 'INNER JOIN meli_tokens t ON t.account_id = a.id '
            . 'WHERE a.id = :account_id'
        );
        $statement->execute(['account_id' => $accountId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            throw new RuntimeException('Billing C0 Mercado Libre account/token is unavailable.');
        }

        if ((string) $row['status'] !== 'connected') {
            throw new RuntimeException('Billing C0 requires a connected Mercado Libre account.');
        }

        $nowUtc = $now->setTimezone(new DateTimeZone('UTC'));
        $expiresAt = (string) $row['expires_at'];
        $expiry = new DateTimeImmutable($expiresAt, new DateTimeZone('UTC'));
        if ($expiry->getTimestamp() <= $nowUtc->getTimestamp() + self::VALIDITY_MARGIN_SECONDS) {
            throw new RuntimeException(
                'Billing C0 requires a stored access token valid for more than 60 seconds; OAuth refresh is disabled.'
            );
        }

        return [
            'account_id' => (int) $row['id'],
            'company_id' => (int) $row['company_id'],
            'site_id' => (string) $row['site_id'],
            'access_token' => $this->cipher->decrypt((string) $row['access_token_cipher']),
            'expires_at' => $expiresAt,
        ];
    }
}
