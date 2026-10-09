<?php

declare(strict_types=1);

namespace App\Modules\Sales\ReconcileOrders;

use App\Integrations\MercadoLibre\Auth\OAuthRefreshService;
use App\Integrations\MercadoLibre\Client\MeliApiException;
use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Client\MeliClientResponse;
use App\Integrations\MercadoLibre\Client\MeliRateLimitException;
use App\Work\WorkRepository;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class ReconcileOrdersHandler
{
    private const REMOTE_RETRY_SECONDS = 30;

    public function __construct(
        private readonly PDO $pdo,
        private readonly WorkRepository $work,
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
        if ($workId < 1 || $claimToken === '' || $companyId < 1 || $accountId < 1) {
            throw new RuntimeException('Invalid order reconciliation identity.');
        }

        try {
            $window = $this->reconciliationWindow($payload);
        } catch (RuntimeException) {
            $this->work->failCurrentClaim(
                $workId,
                $claimToken,
                'orders_reconcile_payload',
                'Order reconciliation payload is invalid.',
            );
            return false;
        }

        $sellerId = $this->connectedSellerId($companyId, $accountId);
        if ($sellerId === null) {
            $this->work->failCurrentClaim(
                $workId,
                $claimToken,
                'orders_reconcile_account',
                'Mercado Libre account is unavailable for order reconciliation.',
            );
            return false;
        }

        $scopeKey = 'company:' . $companyId . ':account:' . $accountId;

        try {
            $accessToken = $this->tokens->getValidAccessToken($accountId, $now);
        } catch (MeliRateLimitException $exception) {
            $this->work->retryCurrentClaim(
                $workId,
                $claimToken,
                $exception->retryAt,
                'meli_rate_limited',
                'Mercado Libre rate limited OAuth before order reconciliation.',
            );
            return false;
        } catch (RuntimeException) {
            $this->work->failCurrentClaim(
                $workId,
                $claimToken,
                'meli_oauth_attention',
                'Mercado Libre OAuth requires attention before order reconciliation.',
            );
            return false;
        }

        try {
            $response = $this->requestPage($sellerId, $accessToken, $scopeKey, $window);
        } catch (MeliRateLimitException $exception) {
            $this->work->retryCurrentClaim(
                $workId,
                $claimToken,
                $exception->retryAt,
                'meli_rate_limited',
                'Mercado Libre rate limited order reconciliation.',
            );
            return false;
        } catch (MeliApiException $exception) {
            if ($exception->status === 401) {
                return $this->retryAfterUnauthorized(
                    $workId,
                    $claimToken,
                    $companyId,
                    $accountId,
                    $sellerId,
                    $scopeKey,
                    $accessToken,
                    $window,
                    $now,
                );
            }

            if ($exception->status >= 500) {
                $this->scheduleRemoteRetry($workId, $claimToken, $now);
                return false;
            }

            $this->work->failCurrentClaim(
                $workId,
                $claimToken,
                'meli_remote_permanent',
                'Mercado Libre rejected order reconciliation.',
            );
            return false;
        } catch (RuntimeException) {
            $this->scheduleRemoteRetry($workId, $claimToken, $now);
            return false;
        }

        return $this->persistPage(
            $workId,
            $claimToken,
            $companyId,
            $accountId,
            $scopeKey,
            $window,
            $response,
        );
    }

    /**
     * @param array{from:string,to:string,offset:int,limit:int} $window
     */
    private function retryAfterUnauthorized(
        int $workId,
        string $claimToken,
        int $companyId,
        int $accountId,
        string $sellerId,
        string $scopeKey,
        string $rejectedAccessToken,
        array $window,
        DateTimeImmutable $now,
    ): bool {
        try {
            $freshAccessToken = $this->tokens->refreshAfterUnauthorized($accountId, $rejectedAccessToken, $now);
            $response = $this->requestPage($sellerId, $freshAccessToken, $scopeKey, $window);
        } catch (MeliRateLimitException $exception) {
            $this->work->retryCurrentClaim(
                $workId,
                $claimToken,
                $exception->retryAt,
                'meli_rate_limited',
                'Mercado Libre rate limited OAuth or retried order reconciliation.',
            );
            return false;
        } catch (MeliApiException $exception) {
            if ($exception->status >= 500) {
                $this->scheduleRemoteRetry($workId, $claimToken, $now);
                return false;
            }

            $this->work->failCurrentClaim(
                $workId,
                $claimToken,
                $exception->status === 401 ? 'meli_unauthorized' : 'meli_remote_permanent',
                'Mercado Libre rejected order reconciliation after token refresh.',
            );
            return false;
        } catch (RuntimeException) {
            $this->scheduleRemoteRetry($workId, $claimToken, $now);
            return false;
        }

        return $this->persistPage(
            $workId,
            $claimToken,
            $companyId,
            $accountId,
            $scopeKey,
            $window,
            $response,
        );
    }

    /**
     * @param array{from:string,to:string,offset:int,limit:int} $window
     */
    private function requestPage(
        string $sellerId,
        string $accessToken,
        string $scopeKey,
        array $window,
    ): MeliClientResponse {
        return $this->client->request(
            'orders.search',
            $accessToken,
            scopeKey: $scopeKey,
            queryParams: [
                'seller' => $sellerId,
                'order.date_created.from' => $window['from'],
                'order.date_created.to' => $window['to'],
                'sort' => 'date_asc',
                'offset' => $window['offset'],
                'limit' => $window['limit'],
            ],
        );
    }

    /**
     * @param array{from:string,to:string,offset:int,limit:int} $window
     */
    private function persistPage(
        int $workId,
        string $claimToken,
        int $companyId,
        int $accountId,
        string $scopeKey,
        array $window,
        MeliClientResponse $response,
    ): bool {
        try {
            $page = $this->normalizePage($response, $window['offset']);
        } catch (RuntimeException) {
            $this->work->failCurrentClaim(
                $workId,
                $claimToken,
                'meli_orders_search_contract',
                'Mercado Libre returned an unusable order search contract.',
            );
            return false;
        }

        return $this->work->completeCurrentClaim(
            $workId,
            $claimToken,
            function (PDO $pdo) use ($companyId, $accountId, $scopeKey, $window, $page): void {
                foreach ($page['order_ids'] as $orderId) {
                    $this->work->enqueue(
                        $companyId,
                        $accountId,
                        $scopeKey,
                        'order.sync',
                        $orderId,
                        'order.sync:' . $orderId,
                        ['order_id' => $orderId],
                    );
                }

                if ($page['next_offset'] === null) {
                    return;
                }

                $nextOffset = $page['next_offset'];
                $resourceKey = $window['from'] . '|' . $window['to'] . '|' . $nextOffset;
                $this->work->enqueue(
                    $companyId,
                    $accountId,
                    $scopeKey,
                    'orders.reconcile',
                    $resourceKey,
                    'orders.reconcile:' . $window['from'] . ':' . $window['to'] . ':' . $nextOffset . ':' . $window['limit'],
                    [
                        'from' => $window['from'],
                        'to' => $window['to'],
                        'offset' => $nextOffset,
                        'limit' => $window['limit'],
                    ],
                );
            },
        );
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{from:string,to:string,offset:int,limit:int}
     */
    private function reconciliationWindow(array $payload): array
    {
        $from = $payload['from'] ?? null;
        $to = $payload['to'] ?? null;
        $offset = $payload['offset'] ?? null;
        $limit = $payload['limit'] ?? null;

        if (!is_string($from) || trim($from) === '' || !is_string($to) || trim($to) === '') {
            throw new RuntimeException('Order reconciliation dates are required.');
        }
        if (!is_int($offset) || $offset < 0 || !is_int($limit) || $limit < 1 || $limit > 50) {
            throw new RuntimeException('Order reconciliation pagination is invalid.');
        }

        try {
            $fromDate = new DateTimeImmutable($from);
            $toDate = new DateTimeImmutable($to);
        } catch (\Exception $exception) {
            throw new RuntimeException('Order reconciliation dates are invalid.', 0, $exception);
        }

        if ($fromDate > $toDate) {
            throw new RuntimeException('Order reconciliation date range is invalid.');
        }

        return [
            'from' => trim($from),
            'to' => trim($to),
            'offset' => $offset,
            'limit' => $limit,
        ];
    }

    private function connectedSellerId(int $companyId, int $accountId): ?string
    {
        $statement = $this->pdo->prepare(
            "SELECT external_user_id FROM meli_accounts "
            . "WHERE id = :account_id AND company_id = :company_id AND status = 'connected' LIMIT 1"
        );
        $statement->execute([
            'account_id' => $accountId,
            'company_id' => $companyId,
        ]);
        $sellerId = $statement->fetchColumn();

        if (!is_string($sellerId) || preg_match('/^[0-9]{1,32}$/D', $sellerId) !== 1) {
            return null;
        }

        return $sellerId;
    }

    /**
     * @return array{order_ids:list<string>,next_offset:?int}
     */
    private function normalizePage(MeliClientResponse $response, int $requestedOffset): array
    {
        $results = $response->data['results'] ?? null;
        $paging = $response->data['paging'] ?? null;
        if (!is_array($results) || !is_array($paging)) {
            throw new RuntimeException('Order search response is incomplete.');
        }

        $total = $this->nonNegativeInt($paging['total'] ?? null);
        $offset = $this->nonNegativeInt($paging['offset'] ?? null);
        $limit = $this->positiveInt($paging['limit'] ?? null);
        if ($total === null || $offset === null || $limit === null || $offset !== $requestedOffset) {
            throw new RuntimeException('Order search paging contract is invalid.');
        }

        $orderIds = [];
        foreach ($results as $result) {
            if (!is_array($result)) {
                throw new RuntimeException('Order search result is malformed.');
            }

            $id = $result['id'] ?? null;
            $orderId = is_int($id) || is_string($id) ? trim((string) $id) : '';
            if (preg_match('/^[0-9]{1,32}$/D', $orderId) !== 1) {
                throw new RuntimeException('Order search result id is invalid.');
            }
            $orderIds[] = $orderId;
        }

        $count = count($orderIds);
        if ($count === 0 && $offset < $total) {
            throw new RuntimeException('Order search returned an empty non-terminal page.');
        }

        $nextOffset = $offset + $count;
        return [
            'order_ids' => $orderIds,
            'next_offset' => $nextOffset < $total ? $nextOffset : null,
        ];
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

    private function scheduleRemoteRetry(int $workId, string $claimToken, DateTimeImmutable $now): void
    {
        $this->work->retryCurrentClaim(
            $workId,
            $claimToken,
            $now->modify('+' . self::REMOTE_RETRY_SECONDS . ' seconds'),
            'meli_remote_retry',
            'Mercado Libre order reconciliation encountered a temporary remote failure.',
        );
    }
}
