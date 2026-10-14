<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Auth\AuthService;
use App\Core\Config\AppConfig;
use App\Core\Database\Connection;
use App\Core\Logging\AppLogger;
use App\Core\Logging\DebugExportService;
use App\Core\Logging\DebugMaintenance;
use App\Core\Logging\DebugRecorder;
use App\Core\Security\Csrf;
use App\Core\Tenancy\CompanyContext;
use App\Integrations\MercadoLibre\Auth\OAuthAuthorizationFlow;
use App\Integrations\MercadoLibre\Auth\OAuthConnectService;
use App\Integrations\MercadoLibre\Auth\TokenCipher;
use App\Integrations\MercadoLibre\Client\ApiUsageRecorder;
use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Client\MeliCooldownRepository;
use App\Integrations\MercadoLibre\Transport\CurlMeliTransport;
use App\Integrations\MercadoLibre\Transport\RemoteHostPolicy;
use App\Modules\Api\ErpApiController;
use App\Modules\Billing\BillingController;
use App\Modules\Catalog\CatalogController;
use App\Modules\Invoicing\InvoicingBridgeController;
use App\Modules\Sales\ReceiveOrderWebhook\OrderWebhookReceiver;
use App\Modules\Sales\ViewSales\SalesListController;
use App\Modules\Settings\SystemSettingsController;
use App\Modules\Settings\SystemSettingsRepository;
use App\Work\WorkRepository;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Slim\App;

final class Routes
{
    /** @param App<ContainerInterface|null> $app */
    public static function register(App $app, AppConfig $config): void
    {
        $app->get('/', static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ) use ($config): ResponseInterface {
            try {
                $context = new CompanyContext(Connection::fromConfig($config));
                $context->assertActiveUser();
                try { $context->companyId(); return $response->withHeader('Location','/sales')->withStatus(303); }
                catch (\DomainException $exception) { if (isset($_SESSION['company_id'])) { throw $exception; } }
                if ($context->memberships() !== []) { return $response->withHeader('Location','/company/select')->withStatus(303); }
                $context->assertSystemAdmin();
                return $response->withHeader('Location','/system/companies')->withStatus(303);
            } catch (\DomainException) {
                return $response->withStatus(403);
            }
        });

        $app->get('/health', static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ): ResponseInterface {
            $response->getBody()->write('{"ok":true}');
            return $response->withHeader('Content-Type', 'application/json');
        });

        $app->map(['GET','POST'], '/system/companies', static function (ServerRequestInterface $request, ResponseInterface $response) use ($config): ResponseInterface {
            return (new CompanyManagementController(Connection::fromConfig($config)))->handle($request,$response);
        });
        $app->post('/system/companies/{id:[0-9]+}/{action:update|archive|reactivate|delete}', static function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($config): ResponseInterface {
            return (new CompanyManagementController(Connection::fromConfig($config)))->handle($request,$response,(string)$args['action'],(int)$args['id']);
        });

        $app->post('/webhooks/mercadolibre', static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ) use ($config): ResponseInterface {
            $payload = $request->getParsedBody();
            if (!is_array($payload)) {
                return $response->withStatus(200);
            }

            $pdo = Connection::fromConfig($config);
            $settings = new SystemSettingsRepository($pdo);
            $runtimeSettings = $settings->get();
            $root = dirname(__DIR__, 3);
            $debugRecorder = new DebugRecorder(
                $root . '/storage/debug',
                $runtimeSettings->debugEnabled,
                $runtimeSettings->debugMaxMb * 1024 * 1024,
                normalLogger: new AppLogger($root . '/storage/logs'),
            );
            $receiver = new OrderWebhookReceiver(
                $pdo,
                new WorkRepository($pdo),
                $config->meliClientId,
                $debugRecorder,
            );
            $receiver->receive($payload);

            return $response->withStatus(200);
        });

        $app->post('/api/products', static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ) use ($config): ResponseInterface {
            return self::erpApiController($config)->createProduct($request, $response);
        });

        $app->get('/api/products/{product_id}', static function (
            ServerRequestInterface $request,
            ResponseInterface $response,
            array $args
        ) use ($config): ResponseInterface {
            return self::erpApiController($config)->readProduct(
                $response,
                (string) ($args['product_id'] ?? ''),
            );
        });

        $app->get('/api/products/{product_id}/inventory', static function (
            ServerRequestInterface $request,
            ResponseInterface $response,
            array $args
        ) use ($config): ResponseInterface {
            return self::erpApiController($config)->readInventory(
                $response,
                (string) ($args['product_id'] ?? ''),
            );
        });

        $app->put('/api/products/{product_id}/inventory', static function (
            ServerRequestInterface $request,
            ResponseInterface $response,
            array $args
        ) use ($config): ResponseInterface {
            return self::erpApiController($config)->setInventory(
                $request,
                $response,
                (string) ($args['product_id'] ?? ''),
            );
        });

        $app->get('/api/integrations/v1/invoicing/sources', static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ) use ($config): ResponseInterface {
            return self::invoicingBridgeController($config)->listSources($request, $response);
        });

        $app->post('/api/integrations/v1/invoicing/sources/{order_id:[0-9]+}/artifact', static function (
            ServerRequestInterface $request,
            ResponseInterface $response,
            array $args
        ) use ($config): ResponseInterface {
            return self::invoicingBridgeController($config)->acceptArtifact(
                $request,
                $response,
                (string) ($args['order_id'] ?? ''),
                'INVOICE',
            );
        });

        $app->post('/api/integrations/v1/invoicing/sources/{order_id:[0-9]+}/credit-note', static function (
            ServerRequestInterface $request,
            ResponseInterface $response,
            array $args
        ) use ($config): ResponseInterface {
            return self::invoicingBridgeController($config)->acceptArtifact(
                $request,
                $response,
                (string) ($args['order_id'] ?? ''),
                'CREDIT_NOTE',
            );
        });

        $app->get('/oauth/mercadolibre/connect', static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ) use ($config): ResponseInterface {
            $userId = $_SESSION['user_id'] ?? null;
            $companyId = $_SESSION['company_id'] ?? null;
            if (!is_int($userId) || $userId < 1 || !is_int($companyId) || $companyId < 1) {
                return $response->withStatus(403);
            }

            $pdo = Connection::fromConfig($config);
            try {
                $company = (new CompanyContext($pdo))->membership($userId,$companyId);
                if ($company['role'] !== 'admin') { return $response->withStatus(403); }
            } catch (\DomainException) { return $response->withStatus(403); }

            if ($config->meliClientId === '') {
                $response->getBody()->write('Mercado Libre no está configurado.');
                return $response->withStatus(503);
            }
            $redirectUri = rtrim($config->appUrl, '/') . '/oauth/mercadolibre/callback';
            $flow = new OAuthAuthorizationFlow(
                $config->meliClientId,
                $redirectUri,
                'https://auth.mercadolibre.com.co/authorization',
            );
            $session =& $_SESSION;
            $authorization = $flow->begin(
                $session,
                new DateTimeImmutable('now', new DateTimeZone('UTC')),
            );
            $_SESSION['meli_oauth_company_id'] = $companyId;

            return $response
                ->withHeader('Location', $authorization['url'])
                ->withStatus(302);
        });

        $app->get('/oauth/mercadolibre/callback', static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ) use ($config): ResponseInterface {
            $query = $request->getQueryParams();
            $authorizationCode = is_string($query['code'] ?? null) ? trim($query['code']) : '';
            $returnedState = is_string($query['state'] ?? null) ? trim($query['state']) : '';
            $userId = $_SESSION['user_id'] ?? null;
            $boundCompanyId = $_SESSION['meli_oauth_company_id'] ?? null;

            if (!is_int($userId) || $userId < 1) {
                unset($_SESSION['meli_oauth'], $_SESSION['meli_oauth_company_id']);
                return $response->withStatus(403);
            }
            if (!is_int($boundCompanyId) || $boundCompanyId < 1 || $authorizationCode === '' || $returnedState === '') {
                unset($_SESSION['meli_oauth'], $_SESSION['meli_oauth_company_id']);
                return $response->withStatus(400);
            }

            $pdo = Connection::fromConfig($config);
            try {
                $company = (new CompanyContext($pdo))->membership($userId,$boundCompanyId);
                if ($company['role'] !== 'admin') { throw new \DomainException('Company admin required.'); }
            } catch (\DomainException) {
                unset($_SESSION['meli_oauth'], $_SESSION['meli_oauth_company_id']);
                return $response->withStatus(403);
            }

            $redirectUri = rtrim($config->appUrl, '/') . '/oauth/mercadolibre/callback';
            $flow = new OAuthAuthorizationFlow(
                $config->meliClientId,
                $redirectUri,
                'https://auth.mercadolibre.com.co/authorization',
            );
            $session =& $_SESSION;
            try {
                $codeVerifier = $flow->consume(
                    $session,
                    $returnedState,
                    new DateTimeImmutable('now', new DateTimeZone('UTC')),
                );
            } catch (RuntimeException) {
                unset($_SESSION['meli_oauth_company_id']);
                return $response->withStatus(400);
            }
            unset($_SESSION['meli_oauth_company_id']);

            /** @var array<string,array{method:string,path:string,family:string,classification:string,official_doc_url:string,verified_at:string}> $operations */
            $operations = require dirname(__DIR__, 3) . '/config/meli_operations.php';
            $settings = new SystemSettingsRepository($pdo);
            $baseUrl = 'https://api.mercadolibre.com';
            if ($config->appEnv === 'test') {
                $testBaseUrl = getenv('MELI_TEST_BASE_URL');
                if ($testBaseUrl !== false && $testBaseUrl !== '') {
                    if (preg_match('~\Ahttp://127\.0\.0\.1:([1-9][0-9]{0,4})\z~', $testBaseUrl, $port) !== 1 || (int) $port[1] > 65535) {
                        throw new RuntimeException('MELI_TEST_BASE_URL must be an HTTP literal loopback URL with a valid port.');
                    }
                    $baseUrl = $testBaseUrl;
                }
            }
            $client = new MeliClient(
                new CurlMeliTransport(new RemoteHostPolicy(), $config->appEnv),
                $settings,
                $operations,
                $baseUrl,
                new ApiUsageRecorder($pdo),
                new MeliCooldownRepository($pdo),
            );
            $connect = new OAuthConnectService(
                $pdo,
                $client,
                new TokenCipher($config->appKey),
                $config->meliClientId,
                $config->meliClientSecret,
                $redirectUri,
            );

            try {
                $connect->connectAuthorizationCode(
                    $boundCompanyId,
                    $authorizationCode,
                    $codeVerifier,
                    new DateTimeImmutable('now', new DateTimeZone('UTC')),
                );
            } catch (RuntimeException) {
                return $response->withStatus(502);
            }

            return $response->withHeader('Location', '/sales')->withStatus(303);
        });

        $app->get('/catalog', static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ) use ($config): ResponseInterface {
            return (new CatalogController(Connection::fromConfig($config)))->index($request, $response);
        });

        $app->post('/catalog', static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ) use ($config): ResponseInterface {
            return (new CatalogController(Connection::fromConfig($config)))->create($request, $response);
        });

        $app->post('/catalog/{product_id:[0-9]+}/inventory', static function (
            ServerRequestInterface $request,
            ResponseInterface $response,
            array $args
        ) use ($config): ResponseInterface {
            return (new CatalogController(Connection::fromConfig($config)))->setInventory(
                $request, $response, (string) ($args['product_id'] ?? ''),
            );
        });

        $app->get('/billing', static function (ServerRequestInterface $request, ResponseInterface $response) use ($config): ResponseInterface {
            return (new BillingController(Connection::fromConfig($config)))->index($request, $response);
        });

        $app->post('/billing/syncs', static function (ServerRequestInterface $request, ResponseInterface $response) use ($config): ResponseInterface {
            return (new BillingController(Connection::fromConfig($config)))->sync($request, $response);
        });

        $app->get('/sales', static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ) use ($config): ResponseInterface {
            return (new SalesListController(Connection::fromConfig($config)))->show($request, $response);
        });

        $app->post('/sales/audits', static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ) use ($config): ResponseInterface {
            return (new SalesListController(Connection::fromConfig($config)))->startAudit($request, $response);
        });

        $app->get('/sales/{order_id:[0-9]+}', static function (
            ServerRequestInterface $request,
            ResponseInterface $response,
            array $args
        ) use ($config): ResponseInterface {
            return (new SalesListController(Connection::fromConfig($config)))->detail(
                $request,
                $response,
                (string) ($args['order_id'] ?? ''),
            );
        });

        $app->get('/login', static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ) use ($config): ResponseInterface {
            $token = (new Csrf())->token();
            $html = '<h1>' . htmlspecialchars($config->appName, ENT_QUOTES, 'UTF-8') . '</h1><h2>Ingresar</h2><form method="post" action="/login">'
                . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">'
                . '<label>Usuario <input name="username" type="text" autocomplete="username" required></label>'
                . '<label>Contraseña <input name="password" type="password" autocomplete="current-password" required></label>'
                . '<button type="submit">Ingresar</button></form>';
            $response->getBody()->write($html);
            return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        $app->post('/login', static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ) use ($config): ResponseInterface {
            $body = $request->getParsedBody();
            $body = is_array($body) ? $body : [];

            try {
                (new Csrf())->assertValid((string) ($body['csrf_token'] ?? ''));
            } catch (RuntimeException) {
                return $response->withStatus(419);
            }

            $auth = new AuthService(Connection::fromConfig($config));
            if (!$auth->login((string) ($body['username'] ?? ''), (string) ($body['password'] ?? ''))) {
                return $response->withStatus(401);
            }

            return $response->withHeader('Location', '/')->withStatus(303);
        });

        $app->post('/logout', static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ) use ($config): ResponseInterface {
            $body = $request->getParsedBody();
            $body = is_array($body) ? $body : [];
            try {
                (new Csrf())->assertValid((string) ($body['csrf_token'] ?? ''));
            } catch (RuntimeException) {
                return $response->withStatus(419);
            }

            (new AuthService(Connection::fromConfig($config)))->logout();
            return $response->withHeader('Location', '/login')->withStatus(303);
        });

        $app->get('/company/select', static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ) use ($config): ResponseInterface {
            $context = new CompanyContext(Connection::fromConfig($config));
            try {
                $companies = $context->memberships();
                $company = null;
                try { $company=$context->company(); }
                catch (\DomainException $exception) { if (isset($_SESSION['company_id'])) { throw $exception; } }
            } catch (\DomainException) {
                return $response->withStatus(403);
            }
            if ($companies === []) {
                return $context->isSystemAdmin() ? $response->withHeader('Location','/system/companies')->withStatus(303) : $response->withStatus(403);
            }
            if ($company !== null && count($companies) < 2) {
                return $response->withHeader('Location', '/sales')->withStatus(303);
            }
            $csrfToken = (new Csrf())->token();
            ob_start();
            require __DIR__ . '/views/company-select.php';
            $response->getBody()->write((string) ob_get_clean());
            return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        $app->post('/company/select', static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ) use ($config): ResponseInterface {
            $body = $request->getParsedBody();
            $body = is_array($body) ? $body : [];
            try {
                (new Csrf())->assertValid((string) ($body['csrf_token'] ?? ''));
            } catch (RuntimeException) {
                return $response->withStatus(419);
            }

            $companyId = filter_var($body['company_id'] ?? null, FILTER_VALIDATE_INT);
            if ($companyId === false || $companyId < 1) {
                return $response->withStatus(422);
            }

            try {
                (new CompanyContext(Connection::fromConfig($config)))->select($companyId);
            } catch (\DomainException) {
                return $response->withStatus(403);
            }

            return $response->withHeader('Location', '/')->withStatus(303);
        });

        $app->map(['GET', 'POST'], '/settings/system', static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ) use ($config): ResponseInterface {
            $controller = self::systemSettingsController($config);

            return $request->getMethod() === 'POST'
                ? $controller->update($request, $response)
                : $controller->show($request, $response);
        });

        $app->post('/settings/system/debug/clear', static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ) use ($config): ResponseInterface {
            $controller = self::systemSettingsController($config);

            return $controller->clearDebug($request, $response);
        });

        $app->post('/settings/system/debug/export', static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ) use ($config): ResponseInterface {
            $controller = self::systemSettingsController($config);

            return $controller->exportDebug($request, $response);
        });
    }

    private static function systemSettingsController(AppConfig $config): SystemSettingsController
    {
        $pdo = Connection::fromConfig($config);
        $root = dirname(__DIR__, 3);
        $debugDirectory = $root . '/storage/debug';
        $exportDirectory = $root . '/storage/exports';

        return new SystemSettingsController(
            $pdo,
            new SystemSettingsRepository($pdo),
            new Csrf(),
            new DebugMaintenance($debugDirectory, $exportDirectory),
            new DebugExportService($debugDirectory, $exportDirectory),
        );
    }

    private static function erpApiController(AppConfig $config): ErpApiController
    {
        return new ErpApiController(Connection::fromConfig($config));
    }

    private static function invoicingBridgeController(AppConfig $config): InvoicingBridgeController
    {
        $root = dirname(__DIR__, 3);
        $storageDirectory = $config->appEnv === 'test'
            ? sys_get_temp_dir() . '/erp-meli2-invoicing-test'
            : $root . '/storage/invoices';

        return new InvoicingBridgeController(Connection::fromConfig($config), $storageDirectory);
    }
}
