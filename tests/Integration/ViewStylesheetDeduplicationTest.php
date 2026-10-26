<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

final class ViewStylesheetDeduplicationTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [
            'user_id' => 1,
            'display_name' => 'UI Tester',
            'is_system_admin' => true,
        ];
        $_SERVER['REQUEST_URI'] = '/';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unset($_SERVER['REQUEST_URI']);
    }

    public function testOperatorViewsLoadSharedStylesheetExactlyOnce(): void
    {
        foreach ([$this->billing(), $this->catalog(), $this->salesList()] as $html) {
            self::assertSame(1, substr_count($html, 'href="/assets/app.css"'));
        }
    }

    public function testCompanySelectorLoadsSharedStylesheetExactlyOnceWithOrWithoutNavigation(): void
    {
        self::assertSame(1, substr_count($this->companySelect(null), 'href="/assets/app.css"'));
        self::assertSame(1, substr_count($this->companySelect(['id' => 1, 'name' => 'Empresa', 'role' => 'admin']), 'href="/assets/app.css"'));
    }

    private function billing(): string
    {
        $accounts = [];
        $periods = [];
        $selection = null;
        $progress = null;
        $requestState = null;
        $error = null;
        $company = ['id' => 1, 'name' => 'Empresa', 'role' => 'admin'];
        $companies = [$company];
        $csrfToken = 'test-token';
        $_SERVER['REQUEST_URI'] = '/billing';

        ob_start();
        require dirname(__DIR__, 2) . '/app/Modules/Billing/views/billing.php';
        return (string) ob_get_clean();
    }

    private function catalog(): string
    {
        $productIdInput = '';
        $selectedProduct = null;
        $quantity = null;
        $error = '';
        $internalSku = '';
        $name = '';
        $search = '';
        $searchInvalid = false;
        $csrfToken = 'test-token';
        $products = [];
        $canOperate = true;
        $company = ['id' => 1, 'name' => 'Empresa', 'role' => 'admin'];
        $companies = [$company];
        $_SERVER['REQUEST_URI'] = '/catalog';

        ob_start();
        require dirname(__DIR__, 2) . '/app/Modules/Catalog/views/catalog.php';
        return (string) ob_get_clean();
    }

    private function salesList(): string
    {
        $orders = [];
        $auditRuns = [];
        $orderId = '';
        $canStartAudit = false;
        $auditAccounts = [];
        $csrfToken = 'test-token';
        $lastClosedPeriodKey = '2026-09-01';
        $company = ['id' => 1, 'name' => 'Empresa', 'role' => 'admin'];
        $companies = [$company];
        $_SERVER['REQUEST_URI'] = '/sales';

        ob_start();
        require dirname(__DIR__, 2) . '/app/Modules/Sales/ViewSales/views/list.php';
        return (string) ob_get_clean();
    }

    /** @param array{id:int,name:string,role:string}|null $selectedCompany */
    private function companySelect(?array $selectedCompany): string
    {
        $company = $selectedCompany;
        $companies = [['id' => 1, 'name' => 'Empresa', 'role' => 'admin']];
        $csrfToken = 'test-token';
        $_SERVER['REQUEST_URI'] = '/company/select';

        ob_start();
        require dirname(__DIR__, 2) . '/app/Core/Http/views/company-select.php';
        return (string) ob_get_clean();
    }
}
