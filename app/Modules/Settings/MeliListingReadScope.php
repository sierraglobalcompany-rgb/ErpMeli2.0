<?php

declare(strict_types=1);

namespace App\Modules\Settings;

use App\Core\Tenancy\CompanyContext;
use App\Integrations\MercadoLibre\Client\MeliClient;
use DomainException;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/** Resolves a company-authorized seller and admits one bounded offline listing page. */
final class MeliListingReadScope
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{account_id:int,company_id:int,seller_id:string,site_id:string} */
    public function connectedSeller(int $accountId): array
    {
        $company = (new CompanyContext($this->pdo))->companyForRoles('admin');
        if ($accountId < 1) {
            throw new DomainException('Connected Mercado Libre account unavailable.');
        }

        $statement = $this->pdo->prepare(
            "SELECT id, company_id, external_user_id, site_id FROM meli_accounts "
            . "WHERE id = :account_id AND company_id = :company_id AND status = 'connected' LIMIT 1"
        );
        $statement->execute([
            'account_id' => $accountId,
            'company_id' => $company['id'],
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new DomainException('Connected Mercado Libre account unavailable.');
        }

        $sellerId = $row['external_user_id'] ?? null;
        $siteId = $row['site_id'] ?? null;
        if (!is_string($sellerId) || preg_match('/^[1-9][0-9]{0,31}$/D', $sellerId) !== 1
            || !is_string($siteId) || preg_match('/^M[A-Z]{2}$/D', $siteId) !== 1) {
            throw new DomainException('Connected Mercado Libre account unavailable.');
        }

        return [
            'account_id' => (int) $row['id'],
            'company_id' => (int) $row['company_id'],
            'seller_id' => $sellerId,
            'site_id' => $siteId,
        ];
    }

    /**
     * Reads at most one verified seller page using the existing client and a server-only token supplier.
     * @param callable(int):mixed $tokenForAccount
     * @return array{account_id:int,seller_id:string,offset:int,next_offset:?int,rows:list<array{id:string,read_state:string,title:?string,status:?string,price:?string,currency_id:?string,public_quantity_reference:?string}>}
     */
    public function readPage(
        int $accountId,
        int $offset,
        int $limit,
        MeliClient $client,
        callable $tokenForAccount,
    ): array {
        if ($offset < 0 || $limit < 1 || $limit > 20 || $offset > 1000 - $limit) {
            throw new InvalidArgumentException('Invalid seller listing page.');
        }

        // Authorization must precede token retrieval and any provider transport.
        $seller = $this->connectedSeller($accountId);
        $accessToken = $tokenForAccount($seller['account_id']);
        if (!is_string($accessToken) || trim($accessToken) === '') {
            throw new RuntimeException('Seller authorization unavailable.');
        }

        $scopeKey = 'company:' . $seller['company_id'] . ':account:' . $seller['account_id'];
        $page = $client->request(
            'users.items.search',
            $accessToken,
            scopeKey: $scopeKey,
            pathParams: ['user_id' => $seller['seller_id']],
            queryParams: ['offset' => $offset, 'limit' => $limit],
        );

        $data = $page->data;
        $paging = $data['paging'] ?? null;
        $ids = $data['results'] ?? null;
        $remoteSeller = $data['seller_id'] ?? null;
        $remoteSeller = is_string($remoteSeller) || is_int($remoteSeller) ? (string) $remoteSeller : null;
        if ($page->status !== 200 || trim($page->contentMissing ?? '') !== ''
            || $remoteSeller !== $seller['seller_id'] || !is_array($paging)
            || !is_int($paging['offset'] ?? null) || $paging['offset'] !== $offset
            || !is_int($paging['limit'] ?? null) || $paging['limit'] !== $limit
            || !is_int($paging['total'] ?? null) || $paging['total'] < 0
            || !is_array($ids) || !array_is_list($ids) || count($ids) > $limit) {
            throw new RuntimeException('Seller listing page cannot be verified.');
        }

        foreach ($ids as $id) {
            if (!is_string($id) || preg_match('/^M[A-Z]{2}[1-9][0-9]{0,31}$/D', $id) !== 1) {
                throw new RuntimeException('Seller listing page contains an invalid item identity.');
            }
        }
        if (count($ids) !== count(array_unique($ids))) {
            throw new RuntimeException('Seller listing page contains duplicate item identities.');
        }

        $expectedCount = min($limit, max(0, $paging['total'] - $offset));
        if (count($ids) !== $expectedCount) {
            throw new RuntimeException('Seller listing page has inconsistent paging.');
        }

        $rows = [];
        if ($ids !== []) {
            $details = $client->request(
                'items.bulk',
                $accessToken,
                scopeKey: $scopeKey,
                resourceCount: count($ids),
                queryParams: ['ids' => implode(',', $ids)],
            );
            $verified = $details->sellerBulkReadState($seller['seller_id'], $ids);
            foreach ($ids as $id) {
                $rows[] = ['id' => $id] + $verified[$id];
            }
        }

        // Preserve discovery order. Never imply a complete inventory scan beyond this bounded page.
        $nextOffset = count($ids) === $limit
            && $offset + $limit < 1000
            && $paging['total'] > $offset + $limit
                ? $offset + $limit
                : null;

        return [
            'account_id' => $seller['account_id'],
            'seller_id' => $seller['seller_id'],
            'offset' => $offset,
            'next_offset' => $nextOffset,
            'rows' => $rows,
        ];
    }
}