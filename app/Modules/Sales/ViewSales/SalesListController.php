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

        ob_start();
        require __DIR__ . '/views/list.php';
        $html = (string) ob_get_clean();

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
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

    private function authorizedCompanyId(): ?int
    {
        $userId = $_SESSION['user_id'] ?? null;
        $companyId = $_SESSION['company_id'] ?? null;
        if (!is_int($userId) || $userId < 1 || !is_int($companyId) || $companyId < 1) {
            return null;
        }

        $membership = $this->pdo->prepare(
            'SELECT 1 FROM company_users WHERE user_id = :user_id AND company_id = :company_id LIMIT 1'
        );
        $membership->execute([
            'user_id' => $userId,
            'company_id' => $companyId,
        ]);

        return $membership->fetchColumn() === false ? null : $companyId;
    }
}
