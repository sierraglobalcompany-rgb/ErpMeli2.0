<?php

declare(strict_types=1);

namespace App\Modules\Dashboard;

use DateTimeImmutable;
use PDO;

final class DashboardReadModel
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array{
     *   orders:int,
     *   products:int,
     *   sales_by_currency:list<array{currency:string,total:string,orders:int}>,
     *   account_statuses:array{connected:int,attention:int,reauth_required:int,disabled:int},
     *   recent_orders:list<array{external_order_id:string,status:string,date_created:string,total_amount:string,currency_id:string}>
     * }
     */
    public function overview(int $companyId, DateTimeImmutable $from, DateTimeImmutable $toExclusive): array
    {
        $fromAt = $from->format('Y-m-d H:i:s.u');
        $toAt = $toExclusive->format('Y-m-d H:i:s.u');

        $ordersStatement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM orders WHERE company_id=:company_id AND date_created>=:from_at AND date_created<:to_at',
        );
        $ordersStatement->execute(['company_id' => $companyId, 'from_at' => $fromAt, 'to_at' => $toAt]);
        $orders = (int) $ordersStatement->fetchColumn();

        $productsStatement = $this->pdo->prepare('SELECT COUNT(*) FROM products WHERE company_id=:company_id');
        $productsStatement->execute(['company_id' => $companyId]);
        $products = (int) $productsStatement->fetchColumn();

        $salesStatement = $this->pdo->prepare(
            'SELECT currency_id, CAST(SUM(total_amount) AS CHAR) AS total_amount, COUNT(*) AS orders_count '
            . 'FROM orders WHERE company_id=:company_id AND date_created>=:from_at AND date_created<:to_at '
            . 'GROUP BY currency_id ORDER BY currency_id',
        );
        $salesStatement->execute(['company_id' => $companyId, 'from_at' => $fromAt, 'to_at' => $toAt]);
        $salesByCurrency = [];
        while (($row = $salesStatement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $salesByCurrency[] = [
                'currency' => (string) $row['currency_id'],
                'total' => (string) $row['total_amount'],
                'orders' => (int) $row['orders_count'],
            ];
        }

        $accountStatuses = ['connected' => 0, 'attention' => 0, 'reauth_required' => 0, 'disabled' => 0];
        $accountsStatement = $this->pdo->prepare(
            'SELECT status, COUNT(*) AS total FROM meli_accounts WHERE company_id=:company_id GROUP BY status',
        );
        $accountsStatement->execute(['company_id' => $companyId]);
        while (($row = $accountsStatement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $status = (string) $row['status'];
            if (array_key_exists($status, $accountStatuses)) {
                $accountStatuses[$status] = (int) $row['total'];
            }
        }

        $recentStatement = $this->pdo->prepare(
            'SELECT external_order_id,status,date_created,total_amount,currency_id FROM orders '
            . 'WHERE company_id=:company_id AND date_created>=:from_at AND date_created<:to_at '
            . 'ORDER BY date_created DESC,id DESC LIMIT 6',
        );
        $recentStatement->execute(['company_id' => $companyId, 'from_at' => $fromAt, 'to_at' => $toAt]);
        $recentOrders = [];
        while (($row = $recentStatement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $recentOrders[] = [
                'external_order_id' => (string) $row['external_order_id'],
                'status' => (string) $row['status'],
                'date_created' => (string) $row['date_created'],
                'total_amount' => (string) $row['total_amount'],
                'currency_id' => (string) $row['currency_id'],
            ];
        }

        return [
            'orders' => $orders,
            'products' => $products,
            'sales_by_currency' => $salesByCurrency,
            'account_statuses' => $accountStatuses,
            'recent_orders' => $recentOrders,
        ];
    }
}
