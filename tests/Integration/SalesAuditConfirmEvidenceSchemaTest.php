<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class SalesAuditConfirmEvidenceSchemaTest extends TestCase
{
    public function testSalesAuditOrdersCanPreserveIndependentCaptureAAndBForTheSameOrder(): void
    {
        $pdo = TestDatabase::reset();

        $columns = $pdo->query('SHOW COLUMNS FROM sales_audit_orders')->fetchAll(PDO::FETCH_ASSOC);
        $byName = [];
        foreach ($columns as $column) {
            $byName[(string) $column['Field']] = $column;
        }

        self::assertArrayHasKey(
            'capture_pass',
            $byName,
            'Independent CONFIRM requires capture A and B evidence to coexist in the same audit run.',
        );

        $primary = $pdo->query("SHOW INDEX FROM sales_audit_orders WHERE Key_name = 'PRIMARY'")
            ->fetchAll(PDO::FETCH_ASSOC);
        $primaryColumns = [];
        foreach ($primary as $index) {
            $primaryColumns[(int) $index['Seq_in_index']] = (string) $index['Column_name'];
        }
        ksort($primaryColumns);
        self::assertSame(
            ['audit_run_id', 'capture_pass', 'external_order_id'],
            array_values($primaryColumns),
            'Capture pass must participate in evidence identity so A is not overwritten by B.',
        );

        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Main','main')");
        $pdo->exec(
            "INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) "
            . "VALUES (1,1,'99887766','MCO','Seller','connected')"
        );
        $pdo->exec(
            "INSERT INTO sales_audit_runs(id,company_id,account_id,period_key,contract_version,status) "
            . "VALUES (1,1,1,'2026-10-01','seller-search-v1','confirming')"
        );

        $statement = $pdo->prepare(
            'INSERT INTO sales_audit_orders '
            . '(audit_run_id,capture_pass,external_order_id,remote_date_created) '
            . 'VALUES (1,:capture_pass,:external_order_id,:remote_date_created)'
        );
        foreach (['A', 'B'] as $capturePass) {
            $statement->execute([
                'capture_pass' => $capturePass,
                'external_order_id' => '200000000001',
                'remote_date_created' => '2026-10-10 15:00:00.000000',
            ]);
        }

        self::assertSame(
            ['A', 'B'],
            $pdo->query(
                "SELECT capture_pass FROM sales_audit_orders "
                . "WHERE audit_run_id=1 AND external_order_id='200000000001' ORDER BY capture_pass"
            )->fetchAll(PDO::FETCH_COLUMN),
        );
    }
}
