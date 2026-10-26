<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\AuthService;
use App\Core\Config\AppConfig;
use App\Core\Http\Routes;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class MeliListingPreviewHttpRouteTest extends TestCase
{
    private const string PATH = '/__test/m02/listings-preview';
    private PDO $pdo;
    private int $companyId;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::reset();
        $auth = new AuthService($this->pdo);
        $auth->bootstrapAdmin('M02 Fixture Company', 'm02-fixture-company', 'm02-admin@example.test', 'M02-preview-test-only-password');
        self::assertTrue($auth->login('m02-admin@example.test', 'M02-preview-test-only-password'));
        $this->companyId = (int) $_SESSION['company_id'];
        $this->pdo->exec("INSERT INTO companies(id,name,slug) VALUES(2,'Foreign company','foreign-company')");

        $insert = $this->pdo->prepare(
            'INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) VALUES(?,?,?,?,?,?)'
        );
        $insert->execute([101, $this->companyId, '910001', 'MCO', 'Cuenta A Demo', 'connected']);
        $insert->execute([102, $this->companyId, '910002', 'MCO', '<svg/onload=alert(1)> Cuenta B', 'connected']);
        $insert->execute([103, $this->companyId, '910003', 'MCO', 'Cuenta desconectada', 'disabled']);
        $insert->execute([201, 2, '920001', 'MCO', 'Cuenta ajena', 'connected']);
    }

    public function testPreviewRouteExistsOnlyInTestAndRendersInsideTheRealShell(): void
    {
        $before = $this->databaseSnapshot();
        $response = $this->request(self::PATH);
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('ERP MELI 2.0', $html);
        self::assertStringContainsString('Vista previa de publicaciones', $html);
        self::assertStringContainsString('Datos de prueba', $html);
        self::assertStringContainsString('no consulta Mercado Libre', $html);
        self::assertStringContainsString('Selecciona una cuenta', $html);
        $accountPosition = strpos($html, 'id="fixture-account-title"');
        $noticePosition = strpos($html, 'data-fixture-notice');
        self::assertIsInt($accountPosition);
        self::assertIsInt($noticePosition);
        self::assertLessThan(
            $noticePosition,
            $accountPosition,
            'The primary account action must precede the long disclosure on mobile.',
        );
        self::assertSame(1, substr_count($html, 'id="main-content"'));
        self::assertStringContainsString('<main id="main-content" data-m02-fixture-preview tabindex="-1">', $html);
        self::assertStringNotContainsString('href="' . self::PATH . '"', $html);
        self::assertSame($before, $this->databaseSnapshot(), 'GET must not mutate ERP data.');

        $localApp = AppFactory::create();
        Routes::register($localApp, AppConfig::fromEnvironment(['APP_ENV' => 'local']));
        foreach ($localApp->getRouteCollector()->getRoutes() as $route) {
            self::assertNotSame(self::PATH, $route->getPattern(), 'Preview route must not register outside APP_ENV=test.');
        }
    }

    public function testSelectedAccountShowsOnlyItsFixtureAndDistinguishesZeroFromUnknown(): void
    {
        $before = $this->databaseSnapshot();
        $response = $this->request(self::PATH, ['account_id' => '101']);
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('MUESTRA-A-001', $html);
        self::assertStringContainsString('Cantidad pública de referencia', $html);
        self::assertStringContainsString('data-fixture-quantity="0">0</span>', $html);
        self::assertStringContainsString('data-fixture-quantity="unknown">Sin información</span>', $html);
        self::assertStringNotContainsString('MUESTRA-B-', $html);
        self::assertStringNotContainsString('public_quantity_reference', $html);
        self::assertSame($before, $this->databaseSnapshot(), 'Preview GET must preserve accounts, tokens, Work, usage, products, and inventory.');
    }

    public function testTwentyRowsPerPageAndAccountChangeResetsFilterOffsetAndDetails(): void
    {
        $firstPage = (string) $this->request(self::PATH, ['account_id' => '101'])->getBody();
        self::assertSame(20, substr_count($firstPage, 'data-fixture-row='));
        self::assertStringContainsString('data-page-offset="0"', $firstPage);
        self::assertStringContainsString('offset=20', $firstPage);

        $secondPageResponse = $this->request(self::PATH, ['account_id' => '101', 'offset' => '20']);
        $secondPage = (string) $secondPageResponse->getBody();
        self::assertSame(200, $secondPageResponse->getStatusCode());
        self::assertSame(1, substr_count($secondPage, 'data-fixture-row='));
        self::assertStringContainsString('data-page-offset="20"', $secondPage);
        self::assertStringNotContainsString('rel="next"', $secondPage);

        $accountB = $this->request(self::PATH, ['account_id' => '102']);
        $html = (string) $accountB->getBody();
        self::assertSame(200, $accountB->getStatusCode());
        self::assertStringContainsString('data-page-offset="0"', $html);
        self::assertStringContainsString('MUESTRA-B-001', $html);
        self::assertStringNotContainsString('MUESTRA-A-', $html);
        self::assertStringNotContainsString('value="20"', $html);
        self::assertStringNotContainsString('<details open', $html);
    }

    public function testFilterIsLimitedToTheVisiblePageAndEscapesItsValue(): void
    {
        $noPageMatch = $this->request(self::PATH, ['account_id' => '101', 'q' => 'MUESTRA-A-021']);
        self::assertSame(200, $noPageMatch->getStatusCode());
        self::assertStringContainsString('Esta página no contiene coincidencias', (string) $noPageMatch->getBody());
        self::assertStringNotContainsString('data-fixture-row="MUESTRA-A-021"', (string) $noPageMatch->getBody());

        $pageTwoMatch = $this->request(self::PATH, ['account_id' => '101', 'offset' => '20', 'q' => 'MUESTRA-A-021']);
        self::assertStringContainsString('MUESTRA-A-021', (string) $pageTwoMatch->getBody());

        $payload = '"><script>alert(1)</script>';
        $xss = $this->request(self::PATH, ['account_id' => '102', 'q' => $payload]);
        $xssHtml = (string) $xss->getBody();
        self::assertSame(200, $xss->getStatusCode());
        self::assertStringNotContainsString('<script>alert(1)</script>', $xssHtml);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $xssHtml);
        self::assertStringContainsString('&lt;svg/onload=alert(1)&gt;', $xssHtml);
    }

    public function testForeignDisabledMissingAndMalformedSelectionsReturnNoRowsOrSellerDetails(): void
    {
        $results = [];
        foreach (['201', '103', '999'] as $accountId) {
            $response = $this->request(self::PATH, ['account_id' => $accountId]);
            $results[] = [$response->getStatusCode(), (string) $response->getBody()];
            self::assertStringNotContainsString('920001', (string) $response->getBody());
            self::assertStringNotContainsString('data-fixture-row=', (string) $response->getBody());
            self::assertStringContainsString('no está disponible', (string) $response->getBody());
        }
        self::assertSame($results[0], $results[1]);
        self::assertSame($results[1], $results[2]);
    }

    public function testConnectedAccountWithoutFixtureShowsExplicitEmptyState(): void
    {
        $insert = $this->pdo->prepare(
            'INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) VALUES(?,?,?,?,?,?)'
        );
        $insert->execute([104, $this->companyId, '910004', 'MCO', 'Cuenta sin fixture', 'connected']);
        $before = $this->databaseSnapshot();

        $response = $this->request(self::PATH, ['account_id' => '104']);
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('No hay una fixture de prueba para esta cuenta.', $html);
        self::assertStringContainsString('Cuenta sin fixture', $html);
        self::assertStringNotContainsString('data-fixture-row=', $html);
        self::assertSame($before, $this->databaseSnapshot(), 'Rendering an empty fixture must not persist rows.');
    }

    public function testNoConnectedAccountsAndInvalidPagingHaveHumanStates(): void
    {
        $this->pdo->prepare("UPDATE meli_accounts SET status='disabled' WHERE company_id=?")->execute([$this->companyId]);
        $empty = $this->request(self::PATH);
        self::assertSame(200, $empty->getStatusCode());
        self::assertStringContainsString('Esta empresa no tiene cuentas conectadas', (string) $empty->getBody());
        self::assertStringNotContainsString('data-fixture-row=', (string) $empty->getBody());

        $invalid = $this->request(self::PATH, ['account_id' => '101', 'offset' => '1000']);
        self::assertSame(422, $invalid->getStatusCode());
        self::assertStringContainsString('página de prueba', (string) $invalid->getBody());
        self::assertStringNotContainsString('data-fixture-row=', (string) $invalid->getBody());

        self::assertSame(422, $this->request(self::PATH, ['account_id' => '101', 'offset' => '1'])->getStatusCode());
    }

    public function testCompanyAdminAuthorizationUsesDatabaseAndActiveUser(): void
    {
        $_SESSION['role'] = 'admin';
        $this->pdo->prepare("UPDATE company_users SET role='member' WHERE user_id=? AND company_id=?")
            ->execute([$_SESSION['user_id'], $this->companyId]);
        $denied = $this->request(self::PATH, ['account_id' => '101']);
        self::assertSame(403, $denied->getStatusCode());
        self::assertSame('No tienes permisos para ver esta vista de prueba.', (string) $denied->getBody());

        $this->pdo->exec("UPDATE users SET status='disabled'");
        self::assertSame(403, $this->request(self::PATH, ['account_id' => '101'])->getStatusCode());

        $this->pdo->exec("UPDATE users SET status='active'");
        $_SESSION = [];
        self::assertSame(403, $this->request(self::PATH, ['account_id' => '101'])->getStatusCode());
    }

    private function request(string $path, array $query = []): ResponseInterface
    {
        return Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', $path)->withQueryParams($query)
        );
    }

    /** @return array<string,list<array<string,mixed>>> */
    private function databaseSnapshot(): array
    {
        $snapshot = [];
        foreach (['users', 'companies', 'company_users', 'meli_accounts', 'meli_tokens', 'work_items', 'api_usage_daily', 'products', 'product_inventory'] as $table) {
            $snapshot[$table] = $this->pdo->query('SELECT * FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC);
        }
        return $snapshot;
    }
}
