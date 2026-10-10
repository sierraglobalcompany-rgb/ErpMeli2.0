<?php

declare(strict_types=1);

namespace App\Modules\Sales\ViewSales;

use App\Core\Security\Csrf;
use App\Modules\Sales\Audit\SalesAuditRepository;
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
        $companyId = $this->authorizedCompanyId();
        if ($companyId === null) {
            return $response->withStatus(403);
        }

        $statement = $this->pdo->prepare(
            'SELECT o.external_order_id, o.status, o.total_amount, o.currency_id, o.date_created, '
            . 'a.external_user_id AS account_external_user_id '
            . 'FROM orders o '
            . 'INNER JOIN meli_accounts a ON a.company_id = o.company_id AND a.id = o.account_id '
            . 'WHERE o.company_id = :company_id '
            . 'ORDER BY COALESCE(o.date_created, o.created_at) DESC, o.id DESC LIMIT 100'
        );
        $statement->execute(['company_id' => $companyId]);
        $orders = $statement->fetchAll(PDO::FETCH_ASSOC);

        $canStartAudit = $this->authorizedCompanyId(adminOnly: true) !== null;
        $auditAccounts = [];
        $csrfToken = '';
        if ($canStartAudit) {
            $accounts = $this->pdo->prepare(
                "SELECT id,external_user_id,nickname FROM meli_accounts "
                . "WHERE company_id = :company_id AND status = 'connected' ORDER BY id"
            );
            $accounts->execute(['company_id' => $companyId]);
            $auditAccounts = $accounts->fetchAll(PDO::FETCH_ASSOC);
            $csrfToken = (new Csrf())->token();
        }

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
        $periodKey = is_string($body['period_key'] ?? null) ? trim($body['period_key']) : '';
        if ($accountId === false || $accountId < 1 || preg_match('/^[0-9]{4}-[0-9]{2}-01$/D', $periodKey) !== 1) {
            return $response->withStatus(422);
        }

        $account = $this->pdo->prepare(
            "SELECT 1 FROM meli_accounts "
            . "WHERE id = :account_id AND company_id = :company_id AND status = 'connected' LIMIT 1"
        );
        $account->execute([
            'account_id' => $accountId,
            'company_id' => $companyId,
        ]);
        if ($account->fetchColumn() === false) {
            return $response->withStatus(422);
        }

        $audit = new SalesAuditRepository($this->pdo);
        $work = new WorkRepository($this->pdo);
        $startedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));

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
        $companyId = $this->authorizedCompanyId();
        if ($companyId === null) {
            return $response->withStatus(403);
        }
        if (preg_match('/^[0-9]{1,32}$/D', $externalOrderId) !== 1) {
            return $response->withStatus(404);
        }

        $statement = $this->pdo->prepare(
            'SELECT o.id, o.external_order_id, o.status, o.status_detail, o.total_amount, o.currency_id, '
            . 'o.date_created, o.date_closed, o.last_updated, o.buyer_id, o.pack_id, '
            . 'a.external_user_id AS account_external_user_id '
            . 'FROM orders o '
            . 'INNER JOIN meli_accounts a ON a.company_id = o.company_id AND a.id = o.account_id '
            . 'WHERE o.company_id = :company_id AND o.external_order_id = :external_order_id LIMIT 1'
        );
        $statement->execute([
            'company_id' => $companyId,
            'external_order_id' => $externalOrderId,
        ]);
        $order = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($order)) {
            return $response->withStatus(404);
        }

        $itemsStatement = $this->pdo->prepare(
            'SELECT external_item_id, variation_id, title, quantity, unit_price, currency_id, seller_sku '
            . 'FROM order_items WHERE order_id = :order_id ORDER BY id'
        );
        $itemsStatement->execute(['order_id' => (int) $order['id']]);
        $items = $itemsStatement->fetchAll(PDO::FETCH_ASSOC);

        ob_start();
        require __DIR__ . '/views/detail.php';
        $html = (string) ob_get_clean();

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function authorizedCompanyId(bool $adminOnly = false): ?int
    {
        $userId = $_SESSION['user_id'] ?? null;
        $companyId = $_SESSION['company_id'] ?? null;
        if (!is_int($userId) || $userId < 1 || !is_int($companyId) || $companyId < 1) {
            return null;
        }

        $membership = $this->pdo->prepare(
            'SELECT role FROM company_users WHERE user_id = :user_id AND company_id = :company_id LIMIT 1'
        );
        $membership->execute([
            'user_id' => $userId,
            'company_id' => $companyId,
        ]);
        $role = $membership->fetchColumn();
        if (!is_string($role) || ($adminOnly && $role !== 'admin')) {
            return null;
        }

        return $companyId;
    }
}
