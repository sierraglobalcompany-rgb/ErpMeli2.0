<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Auth\AuthService;
use App\Core\Config\AppConfig;
use App\Core\Database\Connection;
use App\Core\Security\Csrf;
use App\Core\Tenancy\CompanyContext;
use App\Integrations\MercadoLibre\Auth\OAuthAuthorizationFlow;
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
                'SELECT 1 FROM company_users WHERE user_id = :user_id AND company_id = :company_id LIMIT 1'
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

        $app->get('/sales', static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ) use ($config): ResponseInterface {
            return (new SalesListController(Connection::fromConfig($config)))->show($request, $response);
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
            } catch (\RuntimeException) {
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
            } catch (\RuntimeException) {
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
            } catch (\RuntimeException) {
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
            $controller = new SystemSettingsController(
                $pdo,
                new SystemSettingsRepository($pdo),
                new Csrf(),
            );

            return $request->getMethod() === 'POST'
                ? $controller->update($request, $response)
                : $controller->show($request, $response);
        });
    }
}
