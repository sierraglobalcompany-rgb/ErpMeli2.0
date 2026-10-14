<?php

declare(strict_types=1);

namespace App\Modules\Settings;

use App\Core\Config\AppConfig;
use App\Core\Security\Csrf;
use App\Core\Tenancy\CompanyContext;
use DomainException;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

final class MeliAccountController
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly AppConfig $config,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $context = new CompanyContext($this->pdo);
        try {
            $company = $context->companyForRoles('admin');
        } catch (DomainException) {
            return $response->withStatus(403);
        }

        $statement = $this->pdo->prepare(
            'SELECT id,external_user_id,site_id,nickname,status,updated_at '
            . 'FROM meli_accounts WHERE company_id=? ORDER BY id LIMIT 100'
        );
        $statement->execute([$company['id']]);

        /** @var list<array{id:int|string,external_user_id:string,site_id:string,nickname:?string,status:string,updated_at:string,can_hard_delete?:bool}> $accounts */
        $accounts = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($accounts as $index => $account) {
            $accounts[$index]['can_hard_delete'] = $account['status'] === 'disabled'
                && !$this->hasPersistedHistory(
                    (int) $account['id'],
                    $company['id'],
                    $account['external_user_id'],
                );
        }

        $companies = $context->memberships();
        $csrfToken = (new Csrf())->token();
        $appConfigured = $this->config->meliClientId !== '' && $this->config->meliClientSecret !== '';
        $systemAdmin = $context->isSystemAdmin();

        ob_start();
        require __DIR__ . '/views/meli-accounts.php';
        $response->getBody()->write((string) ob_get_clean());

        return $response
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function disconnect(
        ServerRequestInterface $request,
        ResponseInterface $response,
        int $accountId,
    ): ResponseInterface {
        try {
            $company = (new CompanyContext($this->pdo))->companyForRoles('admin');
        } catch (DomainException) {
            return $response->withStatus(403);
        }

        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        try {
            (new Csrf())->assertValid((string) ($body['csrf_token'] ?? ''));
        } catch (RuntimeException) {
            return $response->withStatus(419);
        }

        $this->pdo->beginTransaction();
        try {
            $account = $this->pdo->prepare(
                'SELECT id FROM meli_accounts WHERE id=? AND company_id=? FOR UPDATE'
            );
            $account->execute([$accountId, $company['id']]);
            if ($account->fetchColumn() === false) {
                $this->pdo->rollBack();
                return $response->withStatus(404);
            }

            $activeWork = $this->pdo->prepare(
                "SELECT EXISTS(SELECT 1 FROM work_items WHERE account_id=? AND status IN ('pending','running'))"
            );
            $activeWork->execute([$accountId]);
            if ((int) $activeWork->fetchColumn() !== 0) {
                $this->pdo->rollBack();
                return $response->withStatus(409);
            }

            $tokens = $this->pdo->prepare('DELETE FROM meli_tokens WHERE account_id=?');
            $tokens->execute([$accountId]);

            $disable = $this->pdo->prepare(
                "UPDATE meli_accounts SET status='disabled', updated_at=UTC_TIMESTAMP(6) WHERE id=? AND company_id=?"
            );
            $disable->execute([$accountId, $company['id']]);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return $response
            ->withHeader('Location', '/mercadolibre/accounts')
            ->withStatus(303);
    }

    public function reauthorize(
        ServerRequestInterface $request,
        ResponseInterface $response,
        int $accountId,
    ): ResponseInterface {
        try {
            $company = (new CompanyContext($this->pdo))->companyForRoles('admin');
        } catch (DomainException) {
            return $response->withStatus(403);
        }

        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        try {
            (new Csrf())->assertValid((string) ($body['csrf_token'] ?? ''));
        } catch (RuntimeException) {
            return $response->withStatus(419);
        }

        unset($_SESSION['meli_oauth_reauthorize_account_id']);
        $account = $this->pdo->prepare('SELECT id FROM meli_accounts WHERE id=? AND company_id=?');
        $account->execute([$accountId, $company['id']]);
        if ($account->fetchColumn() === false) {
            return $response->withStatus(404);
        }

        $_SESSION['meli_oauth_reauthorize_account_id'] = $accountId;

        return $response
            ->withHeader('Location', '/oauth/mercadolibre/connect?reauthorize=1')
            ->withStatus(303);
    }

    public function hardDelete(
        ServerRequestInterface $request,
        ResponseInterface $response,
        int $accountId,
    ): ResponseInterface {
        try {
            $company = (new CompanyContext($this->pdo))->companyForRoles('admin');
        } catch (DomainException) {
            return $response->withStatus(403);
        }

        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        try {
            (new Csrf())->assertValid((string) ($body['csrf_token'] ?? ''));
        } catch (RuntimeException) {
            return $response->withStatus(419);
        }

        $this->pdo->beginTransaction();
        try {
            $account = $this->pdo->prepare(
                'SELECT external_user_id,status FROM meli_accounts WHERE id=? AND company_id=? FOR UPDATE'
            );
            $account->execute([$accountId, $company['id']]);
            $row = $account->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                $this->pdo->rollBack();
                return $response->withStatus(404);
            }
            if (($row['status'] ?? null) !== 'disabled') {
                $this->pdo->rollBack();
                return $response->withStatus(409);
            }

            if ($this->hasPersistedHistory($accountId, $company['id'], (string) $row['external_user_id'])) {
                $this->pdo->rollBack();
                return $response->withStatus(409);
            }

            $delete = $this->pdo->prepare('DELETE FROM meli_accounts WHERE id=? AND company_id=?');
            $delete->execute([$accountId, $company['id']]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return $response
            ->withHeader('Location', '/mercadolibre/accounts')
            ->withStatus(303);
    }

    private function hasPersistedHistory(int $accountId, int $companyId, string $externalUserId): bool
    {
        /** @var list<array{0:string,1:list<int|string>}> $historyChecks */
        $historyChecks = [
            ['SELECT EXISTS(SELECT 1 FROM meli_tokens WHERE account_id=?)', [$accountId]],
            ['SELECT EXISTS(SELECT 1 FROM work_items WHERE account_id=?)', [$accountId]],
            ['SELECT EXISTS(SELECT 1 FROM orders WHERE account_id=?)', [$accountId]],
            ['SELECT EXISTS(SELECT 1 FROM sales_audit_runs WHERE account_id=?)', [$accountId]],
            ['SELECT EXISTS(SELECT 1 FROM billing_periods WHERE account_id=?)', [$accountId]],
            ['SELECT EXISTS(SELECT 1 FROM webhook_events WHERE external_user_id=?)', [$externalUserId]],
            [
                'SELECT EXISTS(SELECT 1 FROM api_usage_daily WHERE scope_key IN (?,?))',
                ['company:' . $companyId . ':account:' . $accountId, 'account:' . $accountId],
            ],
        ];

        foreach ($historyChecks as [$sql, $parameters]) {
            $history = $this->pdo->prepare($sql);
            $history->execute($parameters);
            if ((int) $history->fetchColumn() !== 0) {
                return true;
            }
        }

        return false;
    }
}
