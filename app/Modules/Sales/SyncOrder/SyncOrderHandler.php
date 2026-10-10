<?php

declare(strict_types=1);

namespace App\Modules\Sales\SyncOrder;

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

final class SyncOrderHandler
{
    private const REMOTE_RETRY_SECONDS = 30;

    public function __construct(
        private readonly WorkRepository $work,
        private readonly MeliClient $client,
        private readonly OAuthRefreshService $tokens,
    ) {
    }

    public function syncCurrentClaim(
        int $workId,
        string $claimToken,
        int $companyId,
        int $accountId,
        string $orderId,
        DateTimeImmutable $now,
    ): bool {
        if ($workId < 1 || $companyId < 1 || $accountId < 1 || preg_match('/^[0-9]{1,32}$/D', $orderId) !== 1) {
            throw new RuntimeException('Invalid order sync identity.');
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
                'Mercado Libre rate limited OAuth before order sync.',
            );
            return false;
        } catch (RuntimeException) {
            $this->work->failCurrentClaim(
                $workId,
                $claimToken,
                'meli_oauth_attention',
                'Mercado Libre OAuth requires attention before order sync.',
            );
            return false;
        }

        try {
            $response = $this->requestOrder($orderId, $accessToken, $scopeKey);
        } catch (MeliRateLimitException $exception) {
            $this->work->retryCurrentClaim(
                $workId,
                $claimToken,
                $exception->retryAt,
                'meli_rate_limited',
                'Mercado Libre rate limited the order request.',
            );
            return false;
        } catch (MeliApiException $exception) {
            if ($exception->status === 401) {
                return $this->retryAfterUnauthorized(
                    $workId,
                    $claimToken,
                    $companyId,
                    $accountId,
                    $orderId,
                    $scopeKey,
                    $accessToken,
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
                'Mercado Libre rejected the order request.',
            );
            return false;
        } catch (RuntimeException) {
            // Transport failures and invalid HTTP JSON happen before an authoritative order contract exists.
            $this->scheduleRemoteRetry($workId, $claimToken, $now);
            return false;
        }

        return $this->persistResponse($workId, $claimToken, $companyId, $accountId, $orderId, $response);
    }

    private function retryAfterUnauthorized(
        int $workId,
        string $claimToken,
        int $companyId,
        int $accountId,
        string $orderId,
        string $scopeKey,
        string $rejectedAccessToken,
        DateTimeImmutable $now,
    ): bool {
        try {
            $freshAccessToken = $this->tokens->refreshAfterUnauthorized($accountId, $rejectedAccessToken, $now);
            $response = $this->requestOrder($orderId, $freshAccessToken, $scopeKey);
        } catch (MeliRateLimitException $exception) {
            $this->work->retryCurrentClaim(
                $workId,
                $claimToken,
                $exception->retryAt,
                'meli_rate_limited',
                'Mercado Libre rate limited OAuth or the retried order request.',
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
                'Mercado Libre rejected the order request after token refresh.',
            );
            return false;
        } catch (RuntimeException) {
            $this->scheduleRemoteRetry($workId, $claimToken, $now);
            return false;
        }

        return $this->persistResponse($workId, $claimToken, $companyId, $accountId, $orderId, $response);
    }

    private function requestOrder(string $orderId, string $accessToken, string $scopeKey): MeliClientResponse
    {
        return $this->client->request(
            'orders.get',
            $accessToken,
            pathParams: ['order_id' => $orderId],
            scopeKey: $scopeKey,
        );
    }

    private function persistResponse(
        int $workId,
        string $claimToken,
        int $companyId,
        int $accountId,
        string $orderId,
        MeliClientResponse $response,
    ): bool {
        try {
            $order = $this->normalizeOrder($response->data, $orderId);
        } catch (RuntimeException) {
            $this->work->failCurrentClaim(
                $workId,
                $claimToken,
                'meli_order_contract',
                'Mercado Libre returned an unusable order contract.',
            );
            return false;
        }

        return $this->work->completeCurrentClaim(
            $workId,
            $claimToken,
            function (PDO $pdo) use ($companyId, $accountId, $order): void {
                $existing = $pdo->prepare(
                    'SELECT id, last_updated FROM orders '
                    . 'WHERE company_id = :company_id AND account_id = :account_id AND external_order_id = :external_order_id '
                    . 'FOR UPDATE'
                );
                $existing->execute([
                    'company_id' => $companyId,
                    'account_id' => $accountId,
                    'external_order_id' => $order['external_order_id'],
                ]);
                $row = $existing->fetch(PDO::FETCH_ASSOC);

                if (is_array($row) && $this->isStrictlyNewerLocal((string) ($row['last_updated'] ?? ''), $order['last_updated'])) {
                    return;
                }

                if (is_array($row)) {
                    $localOrderId = (int) $row['id'];
                    $update = $pdo->prepare(
                        'UPDATE orders SET '
                        . 'status = :status, status_detail = :status_detail, date_created = :date_created, '
                        . 'date_closed = :date_closed, last_updated = :last_updated, total_amount = :total_amount, '
                        . 'currency_id = :currency_id, buyer_id = :buyer_id, pack_id = :pack_id, '
                        . 'updated_at = UTC_TIMESTAMP(6) WHERE id = :id'
                    );
                    $update->execute($this->orderParams($order) + ['id' => $localOrderId]);

                    $deleteItems = $pdo->prepare('DELETE FROM order_items WHERE order_id = :order_id');
                    $deleteItems->execute(['order_id' => $localOrderId]);
                } else {
                    $insert = $pdo->prepare(
                        'INSERT INTO orders '
                        . '(company_id, account_id, external_order_id, status, status_detail, date_created, date_closed, '
                        . 'last_updated, total_amount, currency_id, buyer_id, pack_id) '
                        . 'VALUES (:company_id, :account_id, :external_order_id, :status, :status_detail, :date_created, :date_closed, '
                        . ':last_updated, :total_amount, :currency_id, :buyer_id, :pack_id)'
                    );
                    $insert->execute([
                        'company_id' => $companyId,
                        'account_id' => $accountId,
                    ] + $this->orderParams($order) + [
                        'external_order_id' => $order['external_order_id'],
                    ]);
                    $localOrderId = (int) $pdo->lastInsertId();
                }

                $insertItem = $pdo->prepare(
                    'INSERT INTO order_items '
                    . '(order_id, external_item_id, variation_id, title, quantity, unit_price, currency_id, seller_sku) '
                    . 'VALUES (:order_id, :external_item_id, :variation_id, :title, :quantity, :unit_price, :currency_id, :seller_sku)'
                );
                foreach ($order['items'] as $item) {
                    $insertItem->execute(['order_id' => $localOrderId] + $item);
                }
            },
        );
    }

    private function scheduleRemoteRetry(int $workId, string $claimToken, DateTimeImmutable $now): void
    {
        $this->work->retryCurrentClaim(
            $workId,
            $claimToken,
            $now->modify('+' . self::REMOTE_RETRY_SECONDS . ' seconds'),
            'meli_remote_retry',
            'Mercado Libre order sync encountered a temporary remote failure.',
        );
    }

    /**
     * @param array<string,mixed> $data
     * @return array{
     *   external_order_id:string,status:string,status_detail:?string,date_created:string,date_closed:?string,
     *   last_updated:string,total_amount:string,currency_id:string,buyer_id:?string,pack_id:?string,
     *   items:list<array{external_item_id:string,variation_id:?string,title:string,quantity:string,unit_price:string,currency_id:string,seller_sku:?string}>
     * }
     */
    private function normalizeOrder(array $data, string $requestedOrderId): array
    {
        $externalOrderId = $this->scalarString($data['id'] ?? null);
        $status = $this->scalarString($data['status'] ?? null);
        $currencyId = $this->scalarString($data['currency_id'] ?? null);
        $dateCreated = $this->requiredUtcTimestamp($data['date_created'] ?? null, 'date_created');
        $lastUpdated = $this->requiredUtcTimestamp($data['last_updated'] ?? null, 'last_updated');

        if ($externalOrderId !== $requestedOrderId || $status === '' || $currencyId === '') {
            throw new RuntimeException('Mercado Libre order response is incomplete or mismatched.');
        }

        $itemsRaw = $data['order_items'] ?? null;
        if (!is_array($itemsRaw)) {
            throw new RuntimeException('Mercado Libre order items are missing.');
        }

        $items = [];
        foreach ($itemsRaw as $rawItem) {
            if (!is_array($rawItem) || !is_array($rawItem['item'] ?? null)) {
                throw new RuntimeException('Mercado Libre order item is malformed.');
            }

            /** @var array<string,mixed> $itemData */
            $itemData = $rawItem['item'];
            $externalItemId = $this->scalarString($itemData['id'] ?? null);
            $title = $this->scalarString($itemData['title'] ?? null);
            $itemCurrency = $this->scalarString($rawItem['currency_id'] ?? $currencyId);
            if ($externalItemId === '' || $title === '' || $itemCurrency === '') {
                throw new RuntimeException('Mercado Libre order item is incomplete.');
            }

            $items[] = [
                'external_item_id' => $externalItemId,
                'variation_id' => $this->nullableScalarString($itemData['variation_id'] ?? null),
                'title' => mb_substr($title, 0, 255),
                'quantity' => $this->decimal4($rawItem['quantity'] ?? null, 'quantity'),
                'unit_price' => $this->decimal4($rawItem['unit_price'] ?? null, 'unit_price'),
                'currency_id' => mb_substr($itemCurrency, 0, 8),
                'seller_sku' => $this->nullableLimitedString($itemData['seller_sku'] ?? null, 120),
            ];
        }

        $buyer = is_array($data['buyer'] ?? null) ? $data['buyer'] : [];

        return [
            'external_order_id' => $externalOrderId,
            'status' => mb_substr($status, 0, 40),
            'status_detail' => $this->nullableLimitedString($data['status_detail'] ?? null, 80),
            'date_created' => $dateCreated,
            'date_closed' => $this->nullableUtcTimestamp($data['date_closed'] ?? null, 'date_closed'),
            'last_updated' => $lastUpdated,
            'total_amount' => $this->decimal4($data['total_amount'] ?? null, 'total_amount'),
            'currency_id' => mb_substr($currencyId, 0, 8),
            'buyer_id' => $this->nullableScalarString($buyer['id'] ?? null),
            'pack_id' => $this->nullableScalarString($data['pack_id'] ?? null),
            'items' => $items,
        ];
    }

    /**
     * @param array{
     *   external_order_id:string,status:string,status_detail:?string,date_created:string,date_closed:?string,
     *   last_updated:string,total_amount:string,currency_id:string,buyer_id:?string,pack_id:?string,
     *   items:list<array{external_item_id:string,variation_id:?string,title:string,quantity:string,unit_price:string,currency_id:string,seller_sku:?string}>
     * } $order
     * @return array<string,string|null>
     */
    private function orderParams(array $order): array
    {
        return [
            'status' => $order['status'],
            'status_detail' => $order['status_detail'],
            'date_created' => $order['date_created'],
            'date_closed' => $order['date_closed'],
            'last_updated' => $order['last_updated'],
            'total_amount' => $order['total_amount'],
            'currency_id' => $order['currency_id'],
            'buyer_id' => $order['buyer_id'],
            'pack_id' => $order['pack_id'],
        ];
    }

    private function isStrictlyNewerLocal(string $localTimestamp, string $remoteTimestamp): bool
    {
        if ($localTimestamp === '') {
            return false;
        }

        return new DateTimeImmutable($localTimestamp, new DateTimeZone('UTC'))
            > new DateTimeImmutable($remoteTimestamp, new DateTimeZone('UTC'));
    }

    private function scalarString(mixed $value): string
    {
        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }

    private function nullableScalarString(mixed $value): ?string
    {
        $string = $this->scalarString($value);
        return $string === '' ? null : $string;
    }

    private function nullableLimitedString(mixed $value, int $maxLength): ?string
    {
        $string = $this->nullableScalarString($value);
        return $string === null ? null : mb_substr($string, 0, $maxLength);
    }

    private function decimal4(mixed $value, string $field): string
    {
        if (is_int($value)) {
            $value = (string) $value;
        }
        if (!is_string($value)) {
            throw new RuntimeException('Mercado Libre order ' . $field . ' is invalid.');
        }

        $raw = trim($value);
        if (preg_match('/^([+-]?)(\d+)(?:\.(\d+))?$/D', $raw, $matches) !== 1) {
            throw new RuntimeException('Mercado Libre order ' . $field . ' is invalid.');
        }

        $whole = ltrim($matches[2], '0');
        $whole = $whole === '' ? '0' : $whole;
        if (strlen($whole) > 14) {
            throw new RuntimeException('Mercado Libre order ' . $field . ' is invalid.');
        }

        $fraction = $matches[3] ?? '';
        $fiveDigits = substr(str_pad($fraction, 5, '0'), 0, 5);
        $scaled = ((int) $whole * 10_000) + (int) substr($fiveDigits, 0, 4);
        if ((int) $fiveDigits[4] >= 5) {
            ++$scaled;
        }
        if (intdiv($scaled, 10_000) > 99_999_999_999_999) {
            throw new RuntimeException('Mercado Libre order ' . $field . ' is invalid.');
        }

        $sign = $matches[1] === '-' && $scaled !== 0 ? '-' : '';

        return $sign
            . intdiv($scaled, 10_000)
            . '.'
            . str_pad((string) ($scaled % 10_000), 4, '0', STR_PAD_LEFT);
    }

    private function requiredUtcTimestamp(mixed $value, string $field): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException('Mercado Libre order ' . $field . ' is missing.');
        }

        return $this->parseUtcTimestamp($value, $field);
    }

    private function nullableUtcTimestamp(mixed $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException('Mercado Libre order ' . $field . ' is invalid.');
        }

        return $this->parseUtcTimestamp($value, $field);
    }

    private function parseUtcTimestamp(string $value, string $field): string
    {
        $value = trim($value);
        if (preg_match('/(?:Z|[+-][0-9]{2}:[0-9]{2})$/iD', $value) !== 1) {
            throw new RuntimeException('Mercado Libre order ' . $field . ' has no explicit timezone.');
        }

        try {
            return (new DateTimeImmutable($value))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s.u');
        } catch (\Exception) {
            throw new RuntimeException('Mercado Libre order ' . $field . ' is invalid.');
        }
    }
}
