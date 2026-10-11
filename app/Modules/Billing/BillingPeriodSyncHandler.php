<?php

declare(strict_types=1);

namespace App\Modules\Billing;

use App\Integrations\MercadoLibre\Auth\OAuthRefreshService;
use App\Integrations\MercadoLibre\Client\MeliApiException;
use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Client\MeliClientResponse;
use App\Integrations\MercadoLibre\Client\MeliRateLimitException;
use App\Work\WorkRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

final class BillingPeriodSyncHandler
{
    private const REMOTE_RETRY_SECONDS = 30;

    public function __construct(
        private readonly WorkRepository $work,
        private readonly MeliClient $meliClient,
        private readonly OAuthRefreshService $tokens,
        private readonly BillingDetailNormalizer $normalizer,
    ) {
    }

    public function syncCurrentClaim(
        int $workId,
        string $claimToken,
        int $companyId,
        int $accountId,
        string $periodKey,
        string $documentType,
        string $fromId,
        DateTimeImmutable $now,
    ): bool {
        $fromId = trim($fromId);
        $this->assertIdentity($workId, $claimToken, $companyId, $accountId, $periodKey, $documentType, $fromId);

        $scopeKey = 'company:' . $companyId . ':account:' . $accountId;
        try {
            $accessToken = $this->tokens->getValidAccessToken($accountId, $now);
        } catch (MeliRateLimitException $exception) {
            $this->deferRateLimit($workId, $claimToken, $exception);
            return false;
        } catch (RuntimeException) {
            $this->work->failCurrentClaim(
                $workId, $claimToken, 'meli_oauth_attention',
                'Mercado Libre OAuth requires attention before billing period sync.',
            );
            return false;
        }

        try {
            $response = $this->requestPage($accessToken, $scopeKey, $periodKey, $documentType, $fromId);
        } catch (MeliRateLimitException $exception) {
            $this->deferRateLimit($workId, $claimToken, $exception);
            return false;
        } catch (MeliApiException $exception) {
            if ($exception->status !== 401) {
                $this->handleRemoteFailure($workId, $claimToken, $exception, $now);
                return false;
            }

            try {
                $freshAccessToken = $this->tokens->refreshAfterUnauthorized($accountId, $accessToken, $now);
                $response = $this->requestPage($freshAccessToken, $scopeKey, $periodKey, $documentType, $fromId);
            } catch (MeliRateLimitException $recoveryException) {
                $this->deferRateLimit($workId, $claimToken, $recoveryException);
                return false;
            } catch (MeliApiException $recoveryException) {
                $this->handleRemoteFailure($workId, $claimToken, $recoveryException, $now);
                return false;
            } catch (RuntimeException) {
                $this->scheduleRemoteRetry($workId, $claimToken, $now);
                return false;
            }
        } catch (RuntimeException) {
            $this->scheduleRemoteRetry($workId, $claimToken, $now);
            return false;
        }

        if ($response->status === 206) {
            $this->work->deferCurrentClaim(
                $workId, $claimToken, $now->modify('+' . self::REMOTE_RETRY_SECONDS . ' seconds'),
                'meli_billing_partial', 'Mercado Libre billing period page is partial; retry later.',
            );
            return false;
        }

        if (!array_key_exists('results', $response->data) || !array_key_exists('last_id', $response->data)) {
            throw new RuntimeException('Mercado Libre billing period response contract is invalid.');
        }
        $results = $response->data['results'];
        $lastId = $response->data['last_id'];
        if (!is_array($results)) {
            throw new RuntimeException('Mercado Libre billing period response contract is invalid.');
        }
        $isEmptyPage = $results === [];
        if ($isEmptyPage && $lastId !== null && !is_string($lastId) && !is_int($lastId)) {
            throw new RuntimeException('Mercado Libre billing period response contract is invalid.');
        }

        $nextCursor = $fromId;
        if (!$isEmptyPage && (!is_string($lastId) && !is_int($lastId))) {
            throw new RuntimeException('Mercado Libre billing period response contract is invalid.');
        }
        if (!$isEmptyPage) {
            $nextCursor = (string) $lastId;
            if (trim($nextCursor) === '' || strlen($nextCursor) > 40 || $nextCursor === $fromId) {
                throw new RuntimeException('Mercado Libre billing period response contract is invalid.');
            }
        }

        /** @var list<array<string,mixed>> $normalizedDetails */
        $normalizedDetails = [];
        foreach ($results as $result) {
            if (!is_array($result)) {
                throw new RuntimeException('Mercado Libre billing period response contract is invalid.');
            }

            try {
                $normalizedDetails[] = $this->normalizer->normalize($result);
            } catch (Throwable $exception) {
                throw new RuntimeException('Mercado Libre billing detail contract is invalid.', 0, $exception);
            }
        }

        $lastSyncedAt = $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');

        return $this->work->completeCurrentClaim(
            $workId,
            $claimToken,
            function (PDO $pdo) use (
                $companyId,
                $accountId,
                $periodKey,
                $documentType,
                $fromId,
                $isEmptyPage,
                $nextCursor,
                $normalizedDetails,
                $lastSyncedAt,
                $scopeKey,
                $now,
            ): void {
                $periodId = $this->lockPeriod($pdo, $companyId, $accountId, $periodKey, $documentType, $fromId);
                if (!$isEmptyPage) {
                    $this->upsertDetails($pdo, $periodId, $normalizedDetails);
                }

                $state = $isEmptyPage ? 'caught_up' : 'pending';
                $update = $pdo->prepare(
                    'UPDATE billing_periods SET cursor_last_id = :cursor_last_id, sync_state = :sync_state, '
                    . 'partial_flag = 0, last_synced_at = :last_synced_at WHERE id = :id',
                );
                $update->execute([
                    'cursor_last_id' => $nextCursor,
                    'sync_state' => $state,
                    'last_synced_at' => $lastSyncedAt,
                    'id' => $periodId,
                ]);

                if ($isEmptyPage) {
                    return;
                }

                $resourceKey = $periodKey . ':' . $documentType;
                $this->work->enqueue(
                    $companyId,
                    $accountId,
                    $scopeKey,
                    'billing.period.sync',
                    $resourceKey,
                    'billing.period.sync:' . $resourceKey . ':' . $nextCursor,
                    [
                        'period_key' => $periodKey,
                        'document_type' => $documentType,
                        'from_id' => $nextCursor,
                    ],
                    $now,
                );
            },
        );
    }

    private function requestPage(
        string $accessToken,
        string $scopeKey,
        string $periodKey,
        string $documentType,
        string $fromId,
    ): MeliClientResponse {
        return $this->meliClient->request(
            'billing.period.details',
            $accessToken,
            scopeKey: $scopeKey,
            pathParams: ['period_key' => $periodKey],
            queryParams: [
                'document_type' => $documentType,
                'limit' => 1000,
                'from_id' => $fromId,
                'sort_by' => 'ID',
                'order_by' => 'ASC',
            ],
        );
    }

    private function deferRateLimit(int $workId, string $claimToken, MeliRateLimitException $exception): void
    {
        $this->work->deferCurrentClaim(
            $workId, $claimToken, $exception->retryAt,
            'meli_rate_limited', 'Mercado Libre rate limited Billing authorization or request recovery.',
        );
    }

    private function scheduleRemoteRetry(int $workId, string $claimToken, DateTimeImmutable $now): void
    {
        $this->work->retryCurrentClaim(
            $workId, $claimToken, $now->modify('+' . self::REMOTE_RETRY_SECONDS . ' seconds'),
            'meli_remote_retry', 'Mercado Libre billing period sync encountered a temporary remote failure.',
        );
    }

    private function handleRemoteFailure(int $workId, string $claimToken, MeliApiException $exception, DateTimeImmutable $now): void
    {
        if ($exception->status >= 500) {
            $this->scheduleRemoteRetry($workId, $claimToken, $now);
            return;
        }

        $this->work->failCurrentClaim(
            $workId, $claimToken,
            $exception->status === 401 ? 'meli_unauthorized' : 'meli_remote_permanent',
            'Mercado Libre rejected the billing period request.',
        );
    }

    private function assertIdentity(
        int $workId,
        string $claimToken,
        int $companyId,
        int $accountId,
        string $periodKey,
        string $documentType,
        string $fromId,
    ): void {
        $validPeriod = preg_match('/^[0-9]{4}-[0-9]{2}-01$/D', $periodKey) === 1
            && checkdate((int) substr($periodKey, 5, 2), 1, (int) substr($periodKey, 0, 4));

        if ($workId < 1 || trim($claimToken) === '' || $companyId < 1 || $accountId < 1
            || !$validPeriod || !in_array($documentType, ['BILL', 'CREDIT_NOTE'], true)
            || $fromId === '' || strlen($fromId) > 40) {
            throw new RuntimeException('Invalid billing period sync identity.');
        }
    }

    private function lockPeriod(
        PDO $pdo,
        int $companyId,
        int $accountId,
        string $periodKey,
        string $documentType,
        string $fromId,
    ): int {
        $select = $pdo->prepare(
            'SELECT id, cursor_last_id FROM billing_periods '
            . 'WHERE company_id = :company_id AND account_id = :account_id '
            . 'AND period_key = :period_key AND document_type = :document_type FOR UPDATE',
        );
        $parameters = [
            'company_id' => $companyId,
            'account_id' => $accountId,
            'period_key' => $periodKey,
            'document_type' => $documentType,
        ];
        $select->execute($parameters);
        $period = $select->fetch(PDO::FETCH_ASSOC);

        if (!is_array($period)) {
            if ($fromId !== '0') {
                throw new RuntimeException('Billing period cursor changed before page persistence.');
            }

            $insert = $pdo->prepare(
                'INSERT INTO billing_periods (company_id, account_id, period_key, document_type) '
                . 'VALUES (:company_id, :account_id, :period_key, :document_type)',
            );
            $insert->execute($parameters);
            $select->execute($parameters);
            $period = $select->fetch(PDO::FETCH_ASSOC);
        }

        if (!is_array($period) || (string) $period['cursor_last_id'] !== $fromId) {
            throw new RuntimeException('Billing period cursor changed before page persistence.');
        }

        return (int) $period['id'];
    }

    /** @param list<array<string,mixed>> $details */
    private function upsertDetails(PDO $pdo, int $periodId, array $details): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO billing_details '
            . '(billing_period_id, external_detail_id, associated_detail_id, detail_type, detail_sub_type, '
            . 'detail_amount, currency_id, document_id, marketplace, context_json, remote_created_at) '
            . 'VALUES (:billing_period_id, :external_detail_id, :associated_detail_id, :detail_type, :detail_sub_type, '
            . ':detail_amount, :currency_id, :document_id, :marketplace, :context_json, :remote_created_at) '
            . 'ON DUPLICATE KEY UPDATE associated_detail_id = VALUES(associated_detail_id), '
            . 'detail_type = VALUES(detail_type), detail_sub_type = VALUES(detail_sub_type), '
            . 'detail_amount = VALUES(detail_amount), currency_id = VALUES(currency_id), '
            . 'document_id = VALUES(document_id), marketplace = VALUES(marketplace), '
            . 'context_json = VALUES(context_json), remote_created_at = VALUES(remote_created_at), '
            . 'updated_at = UTC_TIMESTAMP(6)',
        );

        foreach ($details as $detail) {
            $statement->execute([
                'billing_period_id' => $periodId,
                'external_detail_id' => $detail['external_detail_id'],
                'associated_detail_id' => $detail['associated_detail_id'],
                'detail_type' => $detail['detail_type'],
                'detail_sub_type' => $detail['detail_sub_type'],
                'detail_amount' => $detail['detail_amount'],
                'currency_id' => $detail['currency_id'],
                'document_id' => $detail['document_id'],
                'marketplace' => $detail['marketplace'],
                'context_json' => $detail['context_json'],
                'remote_created_at' => $detail['remote_created_at'],
            ]);
        }
    }
}
