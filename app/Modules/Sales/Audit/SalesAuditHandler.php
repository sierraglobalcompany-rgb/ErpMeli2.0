<?php

declare(strict_types=1);

namespace App\Modules\Sales\Audit;

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
use UnexpectedValueException;

final class SalesAuditHandler
{
    private const REMOTE_RETRY_SECONDS = 30;
    private const SELLER_SEARCH_HISTORY_MONTHS = 12;

    public function __construct(
        private readonly WorkRepository $work,
        private readonly SalesAuditRepository $audit,
        private readonly MeliClient $client,
        private readonly OAuthRefreshService $tokens,
    ) {
    }

    /** @param array<string,mixed> $payload */
    public function processCurrentClaim(
        int $workId,
        string $claimToken,
        int $companyId,
        int $accountId,
        array $payload,
        DateTimeImmutable $now,
        string $capturePass = 'A',
    ): bool {
        $runId = $payload['run_id'] ?? null;
        $offset = $payload['offset'] ?? null;
        $limit = $payload['limit'] ?? null;
        if (
            $workId < 1
            || $claimToken === ''
            || $companyId < 1
            || $accountId < 1
            || ($capturePass !== 'A' && $capturePass !== 'B')
            || !is_int($runId)
            || $runId < 1
            || !is_int($offset)
            || $offset < 0
            || !is_int($limit)
            || $limit < 1
            || $limit > 50
        ) {
            $this->work->failCurrentClaim(
                $workId,
                $claimToken,
                'sales_audit_payload',
                'Sales audit work payload is invalid.',
            );
            return false;
        }

        $expectedRemoteTotal = $payload['remote_total'] ?? null;
        if (
            $capturePass === 'B'
            && (
                ($expectedRemoteTotal !== null && (!is_int($expectedRemoteTotal) || $expectedRemoteTotal < 0))
                || ($offset > 0 && $expectedRemoteTotal === null)
            )
        ) {
            $this->work->failCurrentClaim(
                $workId,
                $claimToken,
                'sales_audit_payload',
                'Sales audit work payload is invalid.',
            );
            return false;
        }

        $runStatus = $capturePass === 'A' ? 'capturing' : 'confirming';
        try {
            $context = $this->audit->captureContext($runId, $companyId, $accountId, $runStatus);
            $window = SalesAuditWindow::forSitePeriod($context['site_id'], $context['period_key']);
        } catch (RuntimeException|\InvalidArgumentException) {
            $this->work->failCurrentClaim(
                $workId,
                $claimToken,
                'sales_audit_scope',
                'Sales audit capture scope is unavailable.',
            );
            return false;
        }

        $sourceCutoff = $now
            ->setTimezone(new DateTimeZone('UTC'))
            ->modify('-' . self::SELLER_SEARCH_HISTORY_MONTHS . ' months');
        if ($window->canonicalStartUtc < $sourceCutoff) {
            try {
                return $this->work->completeCurrentClaim(
                    $workId,
                    $claimToken,
                    function (PDO $_pdo) use ($runId, $companyId, $accountId, $runStatus): void {
                        $this->audit->markUnavailable($runId, $companyId, $accountId, $runStatus);
                    },
                );
            } catch (RuntimeException) {
                $this->work->failCurrentClaim(
                    $workId,
                    $claimToken,
                    'sales_audit_source_unavailable',
                    'Sales audit source availability could not be persisted.',
                );
                return false;
            }
        }

        try {
            $accessToken = $this->tokens->getValidAccessToken($accountId, $now);
        } catch (MeliRateLimitException $exception) {
            $this->deferRateLimit($workId, $claimToken, $exception, 'Mercado Libre rate limited OAuth before sales audit capture.');
            return false;
        } catch (RuntimeException) {
            $this->work->failCurrentClaim(
                $workId,
                $claimToken,
                'meli_oauth_attention',
                'Mercado Libre OAuth requires attention before sales audit capture.',
            );
            return false;
        }

        try {
            $response = $this->requestSearch(
                $accessToken,
                $companyId,
                $accountId,
                $context,
                $window,
                $offset,
                $limit,
            );
        } catch (MeliRateLimitException $exception) {
            $this->deferRateLimit($workId, $claimToken, $exception, 'Mercado Libre rate limited the sales audit request.');
            return false;
        } catch (MeliApiException $exception) {
            if ($exception->status === 401) {
                $response = $this->retryAfterUnauthorized(
                    $workId,
                    $claimToken,
                    $companyId,
                    $accountId,
                    $context,
                    $window,
                    $offset,
                    $limit,
                    $accessToken,
                    $now,
                );
                if (!$response instanceof MeliClientResponse) {
                    return false;
                }
            } elseif ($exception->status >= 500) {
                $this->scheduleRemoteRetry($workId, $claimToken, $now);
                return false;
            } else {
                $this->work->failCurrentClaim(
                    $workId,
                    $claimToken,
                    'meli_remote_permanent',
                    'Mercado Libre rejected the sales audit request.',
                );
                return false;
            }
        } catch (RuntimeException) {
            $this->scheduleRemoteRetry($workId, $claimToken, $now);
            return false;
        }

        try {
            $page = $this->normalizePage($response, $offset, $limit);
        } catch (RuntimeException) {
            $this->work->failCurrentClaim(
                $workId,
                $claimToken,
                'meli_sales_audit_contract',
                'Mercado Libre returned an unusable sales audit page.',
            );
            return false;
        }

        try {
            return $this->work->completeCurrentClaim(
                $workId,
                $claimToken,
                function (PDO $_pdo) use (
                    $runId,
                    $companyId,
                    $accountId,
                    $limit,
                    $page,
                    $now,
                    $window,
                    $capturePass,
                    $expectedRemoteTotal,
                ): void {
                    if ($capturePass === 'A') {
                        if (!$this->audit->acceptRemoteTotal(
                            $runId,
                            $companyId,
                            $accountId,
                            $page['remote_total'],
                        )) {
                            throw new UnexpectedValueException('Sales audit remote total changed during capture.');
                        }
                    } elseif ($expectedRemoteTotal !== null && $expectedRemoteTotal !== $page['remote_total']) {
                        throw new UnexpectedValueException('Sales audit remote total changed during confirmation capture.');
                    }

                    foreach ($page['observations'] as $observation) {
                        if (!$this->audit->recordObservation(
                            $runId,
                            $observation['external_order_id'],
                            $observation['remote_date_created'],
                            $capturePass,
                        )) {
                            throw new UnexpectedValueException('Sales audit capture contains a duplicate order id.');
                        }
                    }

                    if ($page['next_offset'] === null) {
                        if ($capturePass === 'B') {
                            if ($this->audit->observationCount($runId, 'B') !== $page['remote_total']) {
                                throw new UnexpectedValueException('Sales audit terminal confirmation evidence is incomplete.');
                            }

                            $this->audit->finalizeConfirmation($runId, $companyId, $accountId, $window);
                            return;
                        }

                        if ($this->audit->observationCount($runId) !== $page['remote_total']) {
                            throw new UnexpectedValueException('Sales audit terminal capture evidence is incomplete.');
                        }

                        $this->audit->persistCanonicalFingerprint(
                            $runId,
                            $companyId,
                            $accountId,
                            $window,
                        );

                        $nextStatus = $this->audit->advanceCapturedRun($runId, $companyId, $accountId);
                        if ($nextStatus === 'repairing') {
                            $this->work->enqueue(
                                $companyId,
                                $accountId,
                                'company:' . $companyId . ':account:' . $accountId,
                                'sales.audit',
                                (string) $runId,
                                'sales.audit:' . $runId . ':repair',
                                ['run_id' => $runId],
                                $now,
                            );
                        }
                        return;
                    }

                    $payload = [
                        'run_id' => $runId,
                        'offset' => $page['next_offset'],
                        'limit' => $limit,
                    ];
                    $resourceKey = 'sales.audit:' . $runId . ':' . $page['next_offset'] . ':' . $limit;
                    if ($capturePass === 'B') {
                        $payload['remote_total'] = $expectedRemoteTotal ?? $page['remote_total'];
                        $resourceKey = 'sales.audit:' . $runId . ':confirm:' . $page['next_offset'] . ':' . $limit;
                    }

                    $this->work->enqueue(
                        $companyId,
                        $accountId,
                        'company:' . $companyId . ':account:' . $accountId,
                        'sales.audit',
                        (string) $runId,
                        $resourceKey,
                        $payload,
                        $now,
                    );
                },
            );
        } catch (UnexpectedValueException) {
            $this->work->failCurrentClaim(
                $workId,
                $claimToken,
                'meli_sales_audit_contract',
                'Mercado Libre returned an incoherent sales audit page.',
            );
            return false;
        } catch (RuntimeException) {
            $this->work->failCurrentClaim(
                $workId,
                $claimToken,
                'sales_audit_state',
                'Sales audit lifecycle state could not be persisted.',
            );
            return false;
        }
    }

    /**
     * @param array{period_key:string,site_id:string,seller_id:string} $context
     */
    private function requestSearch(
        string $accessToken,
        int $companyId,
        int $accountId,
        array $context,
        SalesAuditWindow $window,
        int $offset,
        int $limit,
    ): MeliClientResponse {
        return $this->client->request(
            'orders.search',
            $accessToken,
            scopeKey: 'company:' . $companyId . ':account:' . $accountId,
            queryParams: [
                'seller' => $context['seller_id'],
                'order.date_created.from' => $window->remoteFromUtc->format(DATE_ATOM),
                'order.date_created.to' => $window->remoteToUtc->format(DATE_ATOM),
                'sort' => 'date_asc',
                'offset' => $offset,
                'limit' => $limit,
            ],
        );
    }

    /**
     * @param array{period_key:string,site_id:string,seller_id:string} $context
     */
    private function retryAfterUnauthorized(
        int $workId,
        string $claimToken,
        int $companyId,
        int $accountId,
        array $context,
        SalesAuditWindow $window,
        int $offset,
        int $limit,
        string $rejectedAccessToken,
        DateTimeImmutable $now,
    ): ?MeliClientResponse {
        try {
            $freshAccessToken = $this->tokens->refreshAfterUnauthorized($accountId, $rejectedAccessToken, $now);
            return $this->requestSearch(
                $freshAccessToken,
                $companyId,
                $accountId,
                $context,
                $window,
                $offset,
                $limit,
            );
        } catch (MeliRateLimitException $exception) {
            $this->deferRateLimit(
                $workId,
                $claimToken,
                $exception,
                'Mercado Libre rate limited sales audit authorization recovery.',
            );
            return null;
        } catch (MeliApiException $exception) {
            if ($exception->status >= 500) {
                $this->scheduleRemoteRetry($workId, $claimToken, $now);
                return null;
            }

            $this->work->failCurrentClaim(
                $workId,
                $claimToken,
                $exception->status === 401 ? 'meli_unauthorized' : 'meli_remote_permanent',
                'Mercado Libre rejected sales audit after authorization recovery.',
            );
            return null;
        } catch (RuntimeException) {
            $this->scheduleRemoteRetry($workId, $claimToken, $now);
            return null;
        }
    }

    private function deferRateLimit(
        int $workId,
        string $claimToken,
        MeliRateLimitException $exception,
        string $message,
    ): void {
        $this->work->deferCurrentClaim(
            $workId,
            $claimToken,
            $exception->retryAt,
            'meli_rate_limited',
            $message,
        );
    }

    private function scheduleRemoteRetry(int $workId, string $claimToken, DateTimeImmutable $now): void
    {
        $this->work->retryCurrentClaim(
            $workId,
            $claimToken,
            $now->modify('+' . self::REMOTE_RETRY_SECONDS . ' seconds'),
            'meli_remote_retry',
            'Mercado Libre sales audit request failed transiently.',
        );
    }

    /**
     * @return array{
     *   remote_total:int,
     *   next_offset:?int,
     *   observations:list<array{external_order_id:string,remote_date_created:DateTimeImmutable}>
     * }
     */
    private function normalizePage(
        MeliClientResponse $response,
        int $requestedOffset,
        int $requestedLimit,
    ): array {
        $results = $response->data['results'] ?? null;
        $paging = $response->data['paging'] ?? null;
        if (!is_array($results) || !is_array($paging)) {
            throw new RuntimeException('Sales audit page is incomplete.');
        }

        $total = $this->nonNegativeInt($paging['total'] ?? null);
        $offset = $this->nonNegativeInt($paging['offset'] ?? null);
        $limit = $this->positiveInt($paging['limit'] ?? null);
        if (
            $total === null
            || $offset === null
            || $limit === null
            || $offset !== $requestedOffset
            || $limit !== $requestedLimit
        ) {
            throw new RuntimeException('Sales audit paging contract is invalid.');
        }

        $observations = [];
        foreach ($results as $result) {
            if (!is_array($result)) {
                throw new RuntimeException('Sales audit result is malformed.');
            }

            $id = $result['id'] ?? null;
            $orderId = is_int($id) || is_string($id) ? trim((string) $id) : '';
            if (preg_match('/^[0-9]{1,32}$/D', $orderId) !== 1) {
                throw new RuntimeException('Sales audit order id is invalid.');
            }

            $observations[] = [
                'external_order_id' => $orderId,
                'remote_date_created' => $this->requiredZonedTimestamp($result['date_created'] ?? null),
            ];
        }

        if ($observations === [] && $offset < $total) {
            throw new RuntimeException('Sales audit returned an empty non-terminal page.');
        }

        if ($offset > PHP_INT_MAX - $limit) {
            throw new RuntimeException('Sales audit paging offset overflowed.');
        }
        $candidateNextOffset = $offset + $limit;
        if ($candidateNextOffset < $total && count($observations) < $limit) {
            throw new RuntimeException('Sales audit returned a short non-terminal page.');
        }
        $nextOffset = $candidateNextOffset < $total ? $candidateNextOffset : null;

        return [
            'remote_total' => $total,
            'next_offset' => $nextOffset,
            'observations' => $observations,
        ];
    }

    private function requiredZonedTimestamp(mixed $value): DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException('Sales audit date_created is missing.');
        }

        $value = trim($value);
        if (preg_match('/(?:Z|[+-][0-9]{2}:[0-9]{2})$/iD', $value) !== 1) {
            throw new RuntimeException('Sales audit date_created has no explicit timezone.');
        }

        try {
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'));
        } catch (\Exception) {
            throw new RuntimeException('Sales audit date_created is invalid.');
        }
    }

    private function nonNegativeInt(mixed $value): ?int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            return null;
        }

        $int = (int) $value;
        return $int >= 0 ? $int : null;
    }

    private function positiveInt(mixed $value): ?int
    {
        $int = $this->nonNegativeInt($value);
        return $int !== null && $int > 0 ? $int : null;
    }
}
