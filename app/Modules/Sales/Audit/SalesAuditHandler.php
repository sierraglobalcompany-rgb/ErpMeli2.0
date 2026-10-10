<?php

declare(strict_types=1);

namespace App\Modules\Sales\Audit;

use App\Integrations\MercadoLibre\Auth\OAuthRefreshService;
use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Client\MeliClientResponse;
use App\Work\WorkRepository;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class SalesAuditHandler
{
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
    ): bool {
        $runId = $payload['run_id'] ?? null;
        $offset = $payload['offset'] ?? null;
        $limit = $payload['limit'] ?? null;
        if (
            $workId < 1
            || $claimToken === ''
            || $companyId < 1
            || $accountId < 1
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

        try {
            $context = $this->audit->captureContext($runId, $companyId, $accountId);
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

        $accessToken = $this->tokens->getValidAccessToken($accountId, $now);
        $response = $this->client->request(
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

        try {
            $observations = $this->normalizePage($response, $offset);
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
                function () use ($runId, $observations): void {
                    foreach ($observations as $observation) {
                        if (!$this->audit->recordObservation(
                            $runId,
                            $observation['external_order_id'],
                            $observation['remote_date_created'],
                        )) {
                            throw new RuntimeException('Sales audit capture contains a duplicate order id.');
                        }
                    }
                },
            );
        } catch (RuntimeException) {
            $this->work->failCurrentClaim(
                $workId,
                $claimToken,
                'meli_sales_audit_contract',
                'Mercado Libre returned an incoherent sales audit page.',
            );
            return false;
        }
    }

    /**
     * @return list<array{external_order_id:string,remote_date_created:DateTimeImmutable}>
     */
    private function normalizePage(MeliClientResponse $response, int $requestedOffset): array
    {
        $results = $response->data['results'] ?? null;
        $paging = $response->data['paging'] ?? null;
        if (!is_array($results) || !is_array($paging)) {
            throw new RuntimeException('Sales audit page is incomplete.');
        }

        $total = $this->nonNegativeInt($paging['total'] ?? null);
        $offset = $this->nonNegativeInt($paging['offset'] ?? null);
        $limit = $this->positiveInt($paging['limit'] ?? null);
        if ($total === null || $offset === null || $limit === null || $offset !== $requestedOffset) {
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

        return $observations;
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
