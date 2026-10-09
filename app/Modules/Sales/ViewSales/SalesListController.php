<?php

declare(strict_types=1);

namespace App\Modules\Sales\ViewSales;

use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SalesListController
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $_SESSION['user_id'] ?? null;
        $companyId = $_SESSION['company_id'] ?? null;
        if (!is_int($userId) || $userId < 1 || !is_int($companyId) || $companyId < 1) {
            return $response->withStatus(403);
        }

        $membership = $this->pdo->prepare(
            'SELECT 1 FROM company_users WHERE user_id = :user_id AND company_id = :company_id LIMIT 1'
        );
        $membership->execute([
            'user_id' => $userId,
            'company_id' => $companyId,
        ]);
        if ($membership->fetchColumn() === false) {
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

        ob_start();
        require __DIR__ . '/views/list.php';
        $html = (string) ob_get_clean();

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
