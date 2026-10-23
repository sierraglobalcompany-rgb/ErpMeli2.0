<?php

declare(strict_types=1);

namespace App\Modules\Billing;

use App\Core\Security\Csrf;
use App\Core\Tenancy\CompanyContext;
use App\Work\WorkRepository;
use DomainException;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

final class BillingController
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response, ?string $error = null): ResponseInterface
    {
        $context = new CompanyContext($this->pdo);
        try {
            $company = $context->company();
        } catch (DomainException) {
            return $response->withStatus(403);
        }

        $accountsStatement = $this->pdo->prepare("SELECT id,external_user_id,nickname FROM meli_accounts WHERE company_id=? AND status='connected' ORDER BY id");
        $accountsStatement->execute([$company['id']]);
        $accounts = $accountsStatement->fetchAll(PDO::FETCH_ASSOC);
        $periodsStatement = $this->pdo->prepare(
            'SELECT bp.account_id,bp.period_key,bp.document_type,bp.cursor_last_id,bp.sync_state,bp.last_synced_at,a.external_user_id,a.nickname '
            . 'FROM billing_periods bp JOIN meli_accounts a ON a.company_id=bp.company_id AND a.id=bp.account_id '
            . 'WHERE bp.company_id=? ORDER BY bp.period_key DESC,bp.account_id,bp.document_type LIMIT 100',
        );
        $periodsStatement->execute([$company['id']]);
        $periods = $periodsStatement->fetchAll(PDO::FETCH_ASSOC);
        $selection = null;
        $progress = null;
        $requestState = null;
        $query = $request->getQueryParams();
        if ($query !== []) {
            $selection = $this->identity($query);
            if ($selection === null) {
                $error = 'Cuenta, período o tipo documental inválido.';
                $response = $response->withStatus(422);
            } else {
                $account = $this->pdo->prepare('SELECT 1 FROM meli_accounts WHERE company_id=? AND id=?');
                $account->execute([$company['id'], $selection['account_id']]);
                if ($account->fetchColumn() === false) {
                    $selection = null;
                    $error = 'Cuenta no disponible en esta empresa.';
                    $response = $response->withStatus(404);
                } else {
                    $period = $this->pdo->prepare('SELECT cursor_last_id,sync_state,last_synced_at FROM billing_periods WHERE company_id=? AND account_id=? AND period_key=? AND document_type=?');
                    $period->execute([$company['id'], $selection['account_id'], $selection['period_key'], $selection['document_type']]);
                    $row = $period->fetch(PDO::FETCH_ASSOC);
                    $progress = is_array($row) ? $row : null;
                    $requestState = (new WorkRepository($this->pdo))->latestLogicalState(
                        'company:' . $company['id'] . ':account:' . $selection['account_id'],
                        'billing.period.sync',
                        $selection['period_key'] . ':' . $selection['document_type'],
                    );
                }
            }
        }

        $companies = $context->memberships();
        $csrfToken = (new Csrf())->token();
        ob_start();
        require __DIR__ . '/views/billing.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function sync(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $company = (new CompanyContext($this->pdo))->company();
        } catch (DomainException) {
            return $response->withStatus(403);
        }
        if ($company['role'] !== 'admin') {
            return $response->withStatus(403);
        }
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $request = $request->withQueryParams([]);
        try {
            (new Csrf())->assertValid(is_string($body['csrf_token'] ?? null) ? $body['csrf_token'] : '');
        } catch (RuntimeException) {
            return $this->index($request, $response->withStatus(419), 'Sesión del formulario inválida. Vuelva a enviar el formulario.');
        }
        $identity = $this->identity($body);
        if ($identity === null) {
            return $this->index($request, $response->withStatus(422), 'Cuenta, período o tipo documental inválido.');
        }

        $this->pdo->beginTransaction();
        try {
            // Serialize producer requests on the existing account; the handler owns Billing progress.
            $account = $this->pdo->prepare("SELECT id FROM meli_accounts WHERE company_id=? AND id=? AND status='connected' FOR UPDATE");
            $account->execute([$company['id'], $identity['account_id']]);
            if ($account->fetchColumn() === false) {
                $this->pdo->rollBack();
                return $this->index($request, $response->withStatus(422), 'Cuenta no conectada o no disponible en esta empresa.');
            }
            $work = new WorkRepository($this->pdo);
            $scope = 'company:' . $company['id'] . ':account:' . $identity['account_id'];
            $resource = $identity['period_key'] . ':' . $identity['document_type'];
            $latest = $work->latestLogicalState($scope, 'billing.period.sync', $resource);
            if ($latest === null || !in_array($latest['status'], ['pending', 'running'], true)) {
                $period = $this->pdo->prepare('SELECT cursor_last_id FROM billing_periods WHERE company_id=? AND account_id=? AND period_key=? AND document_type=?');
                $period->execute([$company['id'], $identity['account_id'], $identity['period_key'], $identity['document_type']]);
                $cursor = $period->fetchColumn();
                $work->enqueue(
                    $company['id'], $identity['account_id'], $scope, 'billing.period.sync', $resource, $resource,
                    ['period_key' => $identity['period_key'], 'document_type' => $identity['document_type'], 'from_id' => is_string($cursor) ? $cursor : '0'],
                );
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
        return $response->withHeader('Location', '/billing?' . http_build_query($identity, '', '&', PHP_QUERY_RFC3986))->withStatus(303);
    }

    /**
     * @param array<string,mixed> $input
     * @return array{account_id:int,period_key:string,document_type:string}|null
     */
    private function identity(array $input): ?array
    {
        $account = $input['account_id'] ?? null;
        $period = $input['period_key'] ?? null;
        $type = $input['document_type'] ?? null;
        if ((!is_string($account) && !is_int($account)) || preg_match('/^[1-9][0-9]*$/D', (string) $account) !== 1) {
            return null;
        }
        $accountId = filter_var($account, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!is_int($accountId) || !is_string($period) || preg_match('/^[0-9]{4}-[0-9]{2}-01$/D', $period) !== 1
            || !checkdate((int) substr($period, 5, 2), 1, (int) substr($period, 0, 4))
            || !is_string($type) || !in_array($type, ['BILL', 'CREDIT_NOTE'], true)) {
            return null;
        }
        return ['account_id' => $accountId, 'period_key' => $period, 'document_type' => $type];
    }
}
