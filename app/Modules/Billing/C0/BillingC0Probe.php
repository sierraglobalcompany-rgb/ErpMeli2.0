<?php

declare(strict_types=1);

namespace App\Modules\Billing\C0;

use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Client\MeliClientResponse;
use Closure;
use RuntimeException;

final class BillingC0Probe
{
    /** @var Closure(string):MeliClientResponse */
    private readonly Closure $fetchPage;

    /** @param callable(string):MeliClientResponse $fetchPage */
    public function __construct(callable $fetchPage)
    {
        $this->fetchPage = Closure::fromCallable($fetchPage);
    }

    public static function forMeliClient(
        MeliClient $client,
        string $accessToken,
        int $companyId,
        int $accountId,
        string $periodKey,
        string $documentType,
    ): self {
        return new self(static fn (string $cursor): MeliClientResponse => $client->request(
            'billing.period.details',
            $accessToken,
            scopeKey: 'company:' . $companyId . ':account:' . $accountId,
            pathParams: ['period_key' => $periodKey],
            queryParams: [
                'document_type' => $documentType,
                'limit' => 1000,
                'from_id' => $cursor,
                'sort_by' => 'ID',
                'order_by' => 'ASC',
            ],
        ));
    }

    /**
     * @return array{
     *   pages:list<array{
     *     status:int,
     *     cursor_in:string,
     *     results_count:int,
     *     top_level_keys:list<string>,
     *     last_id_type:string,
     *     last_id:int|string|null
     *   }>,
     *   stop_reason:string,
     *   partial_observed:bool,
     *   non_progress_observed:bool
     * }
     */
    public function run(int $maxPages): array
    {
        if ($maxPages < 1) {
            throw new RuntimeException('Billing C0 max pages must be positive.');
        }

        $cursor = '0';
        $pages = [];
        $partialObserved = false;
        $nonProgressObserved = false;
        $stopReason = 'max_pages';

        for ($page = 0; $page < $maxPages; $page++) {
            $response = ($this->fetchPage)($cursor);
            $data = $response->data;
            $results = $data['results'] ?? null;
            $lastId = $data['last_id'] ?? null;

            if (!is_array($results) || !(is_string($lastId) || is_int($lastId) || $lastId === null)) {
                throw new RuntimeException('Billing C0 response contract is invalid.');
            }

            $keys = array_keys($data);
            sort($keys, SORT_STRING);

            $pages[] = [
                'status' => $response->status,
                'cursor_in' => $cursor,
                'results_count' => count($results),
                'top_level_keys' => $keys,
                'last_id_type' => get_debug_type($lastId),
                'last_id' => $lastId,
            ];

            if ($response->status === 206) {
                $partialObserved = true;
                $stopReason = 'partial_206';
                break;
            }

            if ($results === []) {
                $stopReason = 'empty_results';
                break;
            }

            $nextCursor = (string) $lastId;
            if ($nextCursor === '' || $nextCursor === $cursor) {
                $nonProgressObserved = true;
                $stopReason = 'non_progress';
                break;
            }

            $cursor = $nextCursor;
        }

        return [
            'pages' => $pages,
            'stop_reason' => $stopReason,
            'partial_observed' => $partialObserved,
            'non_progress_observed' => $nonProgressObserved,
        ];
    }
}
