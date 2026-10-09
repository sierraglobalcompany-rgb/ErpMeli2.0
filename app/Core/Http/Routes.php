<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Auth\AuthService;
use App\Core\Config\AppConfig;
use App\Core\Database\Connection;
use App\Core\Logging\DebugExportService;
use App\Core\Logging\DebugMaintenance;
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
        $app->get('/health', static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ): ResponseInterface {
            $response->getBody()->write('{"ok":true}');
            return $response->withHeader('Content-Type', 'application/json');
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
            $receiver = new OrderWebhookReceiver(
                $pdo,
                new WorkRepository($pdo),
                $config->meliClientId,
            );
            $receiver->receive($payload);

            return $response->withStatus(200);
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
            $membership = $pdo->prepare(
                "SELECT 1 FROM company_users WHERE user_id = :user_id AND company_id = :company_id AND role = 'admin' LIMIT 1"
            );
            $membership->execute([
                'user_id' => $userId,
                'company_id' => $companyId,
            ]);
            if ($membership->fetchColumn() === false) {
                return $response->withStatus(403);
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
            $membership = $pdo->prepare(
                "SELECT 1 FROM company_users WHERE user_id = :user_id AND company_id = :company_id AND role = 'admin' LIMIT 1"
            );
            $membership->execute([
                'user_id' => $userId,
                'company_id' => $boundCompanyId,
            ]);
            if ($membership->fetchColumn() === false) {
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
            $client = new MeliClient(
                new CurlMeliTransport(new RemoteHostPolicy(), $config->appEnv),
                $settings,
                $operations,
                'https://api.mercadolibre.com',
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

        $app->get('/sales', static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ) use ($config): ResponseInterface {
            return (new SalesListController(Connection::fromConfig($config)))->show($request, $response);
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
        ): ResponseInterface {
            $token = (new Csrf())->token();
            $html = '<form method="post" action="/login">'
                . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">'
                . '<input name="email" type="email" required>'
                . '<input name="password" type="password" required>'
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
            if (!$auth->login((string) ($body['email'] ?? ''), (string) ($body['password'] ?? ''))) {
                return $response->withStatus(401);
            }

            return $response->withHeader('Location', '/settings/system')->withStatus(303);
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
            $pdo = Connection::fromConfig($config);
            $root = dirname(__DIR__, 3);
            $debugDirectory = $root . '/storage/debug';
            $exportDirectory = $root . '/storage/exports';
            $controller = new SystemSettingsController(
                $pdo,
                new SystemSettingsRepository($pdo),
                new Csrf(),
                new DebugMaintenance($debugDirectory, $exportDirectory),
                new DebugExportService($debugDirectory, $exportDirectory),
            );

            return $request->getMethod() === 'POST'
                ? $controller->update($request, $response)
                : $controller->show($request, $response);
        });

        $app->post('/settings/system/debug/clear', static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ) use ($config): ResponseInterface {
            $pdo = Connection::fromConfig($config);
            $root = dirname(__DIR__, 3);
            $debugDirectory = $root . '/storage/debug';
            $exportDirectory = $root . '/storage/exports';
            $controller = new SystemSettingsController(
                $pdo,
                new SystemSettingsRepository($pdo),
                new Csrf(),
                new DebugMaintenance($debugDirectory, $exportDirectory),
                new DebugExportService($debugDirectory, $exportDirectory),
            );

            return $controller->clearDebug($request, $response);
        });

        $app->post('/settings/system/debug/export', static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ) use ($config): ResponseInterface {
            $pdo = Connection::fromConfig($config);
            $root = dirname(__DIR__, 3);
            $debugDirectory = $root . '/storage/debug';
            $exportDirectory = $root . '/storage/exports';
            $controller = new SystemSettingsController(
                $pdo,
                new SystemSettingsRepository($pdo),
                new Csrf(),
                new DebugMaintenance($debugDirectory, $exportDirectory),
                new DebugExportService($debugDirectory, $exportDirectory),
            );

            return $controller->exportDebug($request, $response);
        });
    }
}
