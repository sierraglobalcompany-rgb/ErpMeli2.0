<?php

declare(strict_types=1);

namespace App\Modules\Sales\ViewSales;

use App\Core\Security\Csrf;
use App\Core\Tenancy\CompanyContext;
use App\Modules\Financial\FinancialReadModel;
use App\Modules\Sales\Audit\SalesAuditRepository;
use App\Modules\Sales\Audit\SalesAuditWindow;
use App\Work\WorkRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use PDOException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class SalesListController
{
    private const int AUDIT_PAGE_LIMIT = 50;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $context = new CompanyContext($this->pdo);
        try {
            $company = $context->company();
        } catch (\DomainException) {
            return $response->withStatus(403);
        }
        $companyId = $company['id'];

        $query = $request->getQueryParams();
        $orderId = $query['order_id'] ?? '';
        if (!is_string($orderId) || ($orderId !== '' && preg_match('/^[0-9]{1,32}$/D', $orderId) !== 1)) {
            $response->getBody()->write('<p>ID de venta inválido.</p><a href="/sales">Volver a Ventas</a>');
            return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withStatus(422);
        }
        $parameters = ['company_id' => $companyId];
        if ($orderId !== '') {
            $parameters['order_id'] = $orderId;
        }
        $statement = $this->pdo->prepare(
            'SELECT o.external_order_id, o.status, o.total_amount, o.currency_id, o.date_created, '
            . 'a.external_user_id AS account_external_user_id '
            . 'FROM orders o '
            . 'INNER JOIN meli_accounts a ON a.company_id = o.company_id AND a.id = o.account_id '
            . 'WHERE o.company_id = :company_id '
            . ($orderId === '' ? '' : 'AND o.external_order_id = :order_id ')
            . 'ORDER BY COALESCE(o.date_created, o.created_at) DESC, o.id DESC LIMIT 100'
        );
        $statement->execute($parameters);
        $orders = $statement->fetchAll(PDO::FETCH_ASSOC);

        $auditRuns = (new SalesAuditRepository($this->pdo))->recentForCompany($companyId);
        $canStartAudit = $company['role'] === 'admin';
        $auditAccounts = [];
        $lastClosedPeriodKey = '';
        if ($canStartAudit) {
            $accounts = $this->pdo->prepare(
                "SELECT id,external_user_id,nickname FROM meli_accounts "
                . "WHERE company_id = :company_id AND status = 'connected' ORDER BY id"
            );
            $accounts->execute(['company_id' => $companyId]);
            $auditAccounts = $accounts->fetchAll(PDO::FETCH_ASSOC);
            $lastClosedPeriodKey = SalesAuditWindow::lastClosedPeriodKey(
                new DateTimeImmutable('now', new DateTimeZone('UTC')),
            );
        }

        $companies = $context->memberships();
        $csrfToken = (new Csrf())->token();
        ob_start();
        require __DIR__ . '/views/list.php';
        $html = (string) ob_get_clean();

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function startAudit(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $companyId = $this->authorizedCompanyId(adminOnly: true);
        if ($companyId === null) {
            return $response->withStatus(403);
        }

        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        try {
            (new Csrf())->assertValid((string) ($body['csrf_token'] ?? ''));
        } catch (\RuntimeException) {
            return $response->withStatus(419);
        }

        $accountId = filter_var($body['account_id'] ?? null, FILTER_VALIDATE_INT);
        $periodInput = is_string($body['period_key'] ?? null) ? trim($body['period_key']) : '';
        $periodKey = preg_match('/^[0-9]{4}-[0-9]{2}$/D', $periodInput) === 1
            ? $periodInput . '-01'
            : $periodInput;
        if ($accountId === false || $accountId < 1 || preg_match('/^[0-9]{4}-[0-9]{2}-01$/D', $periodKey) !== 1) {
            return $response->withStatus(422);
        }

        $account = $this->pdo->prepare(
            "SELECT site_id FROM meli_accounts "
            . "WHERE id = :account_id AND company_id = :company_id AND status = 'connected' LIMIT 1"
        );
        $account->execute([
            'account_id' => $accountId,
            'company_id' => $companyId,
        ]);
        $siteId = $account->fetchColumn();
        if (!is_string($siteId) || $siteId === '') {
            return $response->withStatus(422);
        }

        $startedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        try {
            $window = SalesAuditWindow::forSitePeriod($siteId, $periodKey);
            if (!$window->isClosedAt($startedAt)) {
                $response->getBody()->write('Solo se pueden auditar meses cerrados.');
                return $response
                    ->withHeader('Content-Type', 'text/plain; charset=utf-8')
                    ->withStatus(422);
            }
        } catch (InvalidArgumentException) {
            return $response->withStatus(422);
        }

        $audit = new SalesAuditRepository($this->pdo);
        $work = new WorkRepository($this->pdo);

        $this->pdo->beginTransaction();
        try {
            $runId = $audit->createCapturingRun(
                $companyId,
                $accountId,
                $periodKey,
                SalesAuditRepository::CONTRACT_VERSION,
                $startedAt,
            );
            $work->enqueue(
                $companyId,
                $accountId,
                'company:' . $companyId . ':account:' . $accountId,
                'sales.audit',
                (string) $runId,
                'sales.audit:' . $runId . ':0:' . self::AUDIT_PAGE_LIMIT,
                [
                    'run_id' => $runId,
                    'offset' => 0,
                    'limit' => self::AUDIT_PAGE_LIMIT,
                ],
                $startedAt,
            );
            $this->pdo->commit();
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($exception->getCode() === '23000' && (int) ($exception->errorInfo[1] ?? 0) === 1062) {
                $response->getBody()->write('Ya existe una auditoría activa para esta cuenta y período.');
                return $response
                    ->withHeader('Content-Type', 'text/plain; charset=utf-8')
                    ->withStatus(409);
            }

            throw $exception;
        } catch (InvalidArgumentException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return $response->withStatus(422);
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return $response
            ->withHeader('Location', '/sales')
            ->withStatus(303);
    }

    public function detail(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $externalOrderId,
    ): ResponseInterface {
        $context = new CompanyContext($this->pdo);
        try {
            $company = $context->company();
        } catch (\DomainException) {
            return $response->withStatus(403);
        }
        $companyId = $company['id'];
        if (preg_match('/^[0-9]{1,32}$/D', $externalOrderId) !== 1) {
            return $response->withStatus(404);
        }

        $statement = $this->pdo->prepare(
            'SELECT o.id, o.account_id, o.external_order_id, o.status, o.status_detail, o.total_amount, o.currency_id, '
            . 'o.date_created, o.date_closed, o.last_updated, o.buyer_id, o.pack_id, '
            . 'a.external_user_id AS account_external_user_id '
            . 'FROM orders o '
            . 'INNER JOIN meli_accounts a ON a.company_id = o.company_id AND a.id = o.account_id '
            . 'WHERE o.company_id = :company_id AND o.external_order_id = :external_order_id LIMIT 2'
        );
        $statement->execute([
            'company_id' => $companyId,
            'external_order_id' => $externalOrderId,
        ]);
        $matchingOrders = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (count($matchingOrders) !== 1) {
            return $response->withStatus(404);
        }
        $order = $matchingOrders[0];

        $itemsStatement = $this->pdo->prepare(
            'SELECT external_item_id, variation_id, title, quantity, unit_price, currency_id, seller_sku '
            . 'FROM order_items WHERE order_id = :order_id ORDER BY id'
        );
        $itemsStatement->execute(['order_id' => (int) $order['id']]);
        $items = $itemsStatement->fetchAll(PDO::FETCH_ASSOC);

        $reconciliation = (new FinancialReadModel($this->pdo))->forOrder($companyId, (int) $order['account_id'], (string) $order['external_order_id']);
        $companies = $context->memberships();
        $csrfToken = (new Csrf())->token();
        ob_start();
        require __DIR__ . '/views/detail.php';
        $html = (string) ob_get_clean();

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function authorizedCompanyId(bool $adminOnly = false): ?int
    {
        try {
            $company = (new CompanyContext($this->pdo))->company();
            return $adminOnly && $company['role'] !== 'admin' ? null : $company['id'];
        } catch (\DomainException) {
            return null;
        }
    }
}
