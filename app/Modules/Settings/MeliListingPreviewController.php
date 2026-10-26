<?php

declare(strict_types=1);

namespace App\Modules\Settings;

use App\Core\Security\Csrf;
use App\Core\Tenancy\CompanyContext;
use DomainException;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Test-only presentation of static, account-bound listing fixtures. */
final class MeliListingPreviewController
{
    public const string PATH = '/__test/m02/listings-preview';
    private const int PAGE_SIZE = 20;
    private const int MAX_WINDOW = 1000;
    private const int MAX_FILTER_LENGTH = 80;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $context = new CompanyContext($this->pdo);
        try {
            $company = $context->companyForRoles('admin');
        } catch (DomainException) {
            $response->getBody()->write('No tienes permisos para ver esta vista de prueba.');
            return $response
                ->withStatus(403)
                ->withHeader('Cache-Control', 'no-store')
                ->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        $accountStatement = $this->pdo->prepare(
            "SELECT id, external_user_id, site_id, nickname FROM meli_accounts "
            . "WHERE company_id = :company_id AND status = 'connected' ORDER BY id LIMIT 100"
        );
        $accountStatement->execute(['company_id' => $company['id']]);
        /** @var list<array{id:int|string,external_user_id:string,site_id:string,nickname:?string}> $storedAccounts */
        $storedAccounts = $accountStatement->fetchAll(PDO::FETCH_ASSOC);
        $accounts = array_map(
            static fn (array $account): array => [
                'id' => (int) $account['id'],
                'seller_id' => $account['external_user_id'],
                'site_id' => $account['site_id'],
                'nickname' => $account['nickname'],
            ],
            $storedAccounts,
        );

        $query = $request->getQueryParams();
        $accountInput = $query['account_id'] ?? '';
        $hasAccountInput = $accountInput !== '';
        $accountId = $hasAccountInput ? self::positiveId($accountInput) : null;
        $offset = self::offset($query['offset'] ?? null);
        $filterInput = $query['q'] ?? '';
        $filter = is_string($filterInput) ? trim($filterInput) : '';
        $error = '';
        $responseStatus = 200;

        if (($hasAccountInput && $accountId === null) || $offset === null) {
            $error = 'Revisa la cuenta o el número de página de prueba.';
            $responseStatus = 422;
            $accountId = null;
            $offset = 0;
        } elseif (!is_string($filterInput) || mb_strlen($filter, 'UTF-8') > self::MAX_FILTER_LENGTH) {
            $error = 'El filtro debe ser texto de máximo 80 caracteres.';
            $responseStatus = 422;
            $filter = '';
        }

        $selectedAccount = null;
        $selectedSeller = null;
        $fixtures = [];
        if ($responseStatus === 200 && $accountId !== null) {
            try {
                $selectedSeller = (new MeliListingReadScope($this->pdo))->connectedSeller($accountId);
            } catch (DomainException) {
                $error = 'La cuenta solicitada no está disponible para esta empresa o no está conectada.';
                $responseStatus = 404;
            }

            if ($responseStatus === 200) {
                foreach ($accounts as $account) {
                    if ($account['id'] === $accountId) {
                        $selectedAccount = $account;
                        break;
                    }
                }
                if ($selectedAccount === null) {
                    $error = 'La cuenta solicitada no está disponible para esta empresa o no está conectada.';
                    $responseStatus = 404;
                } else {
                    $fixtures = self::fixturesFor($accountId, $selectedSeller['seller_id']) ?? [];
                }
            }
        }

        $pageRows = array_slice($fixtures, $offset, self::PAGE_SIZE);
        $visibleRows = $filter === '' ? $pageRows : array_values(array_filter(
            $pageRows,
            static fn (array $row): bool => str_contains(mb_strtolower($row['id'] . ' ' . ($row['title'] ?? ''), 'UTF-8'), mb_strtolower($filter, 'UTF-8')),
        ));
        $total = min(count($fixtures), self::MAX_WINDOW);
        $nextOffset = count($pageRows) === self::PAGE_SIZE
            && $offset + self::PAGE_SIZE < $total
                ? $offset + self::PAGE_SIZE
                : null;
        $previousOffset = $offset > 0 ? $offset - self::PAGE_SIZE : null;
        $companies = $context->memberships();
        $csrfToken = (new Csrf())->token();

        ob_start();
        require __DIR__ . '/views/meli-listings-preview.php';
        $response->getBody()->write((string) ob_get_clean());

        return $response
            ->withStatus($responseStatus)
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private static function positiveId(mixed $value): ?int
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $text = (string) $value;
        if (preg_match('/^[1-9][0-9]{0,17}$/D', $text) !== 1) {
            return null;
        }
        $id = filter_var($text, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return is_int($id) ? $id : null;
    }

    private static function offset(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $text = (string) $value;
        if (preg_match('/^(0|[1-9][0-9]{0,2})$/D', $text) !== 1) {
            return null;
        }
        $offset = (int) $text;
        return $offset % self::PAGE_SIZE === 0 && $offset + self::PAGE_SIZE <= self::MAX_WINDOW
            ? $offset
            : null;
    }

    /** @return list<array{id:string,title:?string,identity:string,status:?string,price:?string,currency_id:?string,public_quantity_reference:?string}>|null */
    private static function fixturesFor(int $accountId, string $sellerId): ?array
    {
        if ($accountId === 101 && $sellerId === '910001') {
            $rows = [
                ['id' => 'MUESTRA-A-001', 'title' => 'Camisa de algodón (ejemplo)', 'identity' => 'matched', 'status' => 'Activa (muestra)', 'price' => '0.00', 'currency_id' => 'COP', 'public_quantity_reference' => '0'],
                ['id' => 'MUESTRA-A-002', 'title' => null, 'identity' => 'unknown', 'status' => null, 'price' => null, 'currency_id' => null, 'public_quantity_reference' => null],
            ];
            foreach (range(3, 21) as $number) {
                $rows[] = [
                    'id' => sprintf('MUESTRA-A-%03d', $number),
                    'title' => 'Artículo ficticio ' . $number,
                    'identity' => 'unknown',
                    'status' => null,
                    'price' => null,
                    'currency_id' => null,
                    'public_quantity_reference' => null,
                ];
            }
            return $rows;
        }

        if ($accountId === 102 && $sellerId === '910002') {
            return [
                ['id' => 'MUESTRA-B-001', 'title' => 'Chaqueta de muestra', 'identity' => 'matched', 'status' => 'Activa (muestra)', 'price' => '159900.00', 'currency_id' => 'COP', 'public_quantity_reference' => '3'],
                ['id' => 'MUESTRA-B-002', 'title' => 'Publicación sin datos completos', 'identity' => 'unknown', 'status' => null, 'price' => null, 'currency_id' => null, 'public_quantity_reference' => null],
                ['id' => 'MUESTRA-B-003', 'title' => 'Camisa <script>alert(1)</script>', 'identity' => 'unknown', 'status' => null, 'price' => null, 'currency_id' => null, 'public_quantity_reference' => null],
            ];
        }

        return null;
    }
}
