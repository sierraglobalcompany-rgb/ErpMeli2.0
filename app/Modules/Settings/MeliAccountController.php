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

        /** @var list<array{id:int|string,external_user_id:string,site_id:string,nickname:?string,status:string,updated_at:string}> $accounts */
        $accounts = $statement->fetchAll(PDO::FETCH_ASSOC);
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
}
