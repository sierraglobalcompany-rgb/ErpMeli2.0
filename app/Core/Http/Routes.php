<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Auth\AuthService;
use App\Core\Config\AppConfig;
use App\Core\Database\Connection;
use App\Core\Security\Csrf;
use App\Core\Tenancy\CompanyContext;
use App\Modules\Settings\SystemSettingsController;
use App\Modules\Settings\SystemSettingsRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

final class Routes
{
    public static function register(App $app, AppConfig $config): void
    {
        $app->get('/health', static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ): ResponseInterface {
            $response->getBody()->write('{"ok":true}');
            return $response->withHeader('Content-Type', 'application/json');
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
