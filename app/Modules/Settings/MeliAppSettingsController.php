<?php

declare(strict_types=1);

namespace App\Modules\Settings;

use App\Core\Config\AppConfig;
use App\Core\Config\Environment;
use App\Core\Install\Installation;
use App\Core\Security\Csrf;
use App\Core\Tenancy\CompanyContext;
use DomainException;
use InvalidArgumentException;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

final class MeliAppSettingsController
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly AppConfig $config,
        private readonly string $root,
    ) {
    }

    public function handle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            (new CompanyContext($this->pdo))->assertSystemAdmin();
        } catch (DomainException) {
            return $response->withStatus(403);
        }

        $response = $response
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Content-Type', 'text/html; charset=utf-8');

        $installation = new Installation($this->root);
        if ($installation->state() !== 'installed') {
            return $response->withStatus(503);
        }

        $serverManaged = Environment::isExternallyManaged('MELI_CLIENT_ID')
            || Environment::isExternallyManaged('MELI_CLIENT_SECRET');

        if ($request->getMethod() === 'GET') {
            return $this->render($response, $serverManaged);
        }

        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        try {
            (new Csrf())->assertValid(is_string($body['csrf_token'] ?? null) ? $body['csrf_token'] : '');
        } catch (RuntimeException) {
            return $response->withStatus(419);
        }

        if ($serverManaged) {
            $response->getBody()->write('Las credenciales de Mercado Libre están administradas por el servidor.');
            return $response->withStatus(409);
        }

        try {
            $this->mutate($installation, $body);
        } catch (InvalidArgumentException $exception) {
            $response->getBody()->write($exception->getMessage());
            return $response->withStatus(422);
        } catch (DomainException $exception) {
            $response->getBody()->write($exception->getMessage());
            return $response->withStatus(409);
        } catch (RuntimeException) {
            $response->getBody()->write('No se pudo actualizar la configuración privada.');
            return $response->withStatus(503);
        }

        return $response->withHeader('Location', '/settings/mercadolibre')->withStatus(303);
    }

    private function render(ResponseInterface $response, bool $serverManaged): ResponseInterface
    {
        $clientId = $this->config->meliClientId;
        $secretConfigured = $this->config->meliClientSecret !== '';
        $status = $serverManaged
            ? 'Administrada por servidor'
            : ($clientId !== '' && $secretConfigured ? 'Configurada' : 'Pendiente');
        $baseUrl = rtrim($this->config->appUrl, '/');
        $oauthCallback = $baseUrl . '/oauth/mercadolibre/callback';
        $webhookCallback = $baseUrl . '/webhooks/mercadolibre';
        $csrfToken = (new Csrf())->token();

        ob_start();
        require __DIR__ . '/views/mercadolibre.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response;
    }

    /** @param array<mixed> $body */
    private function mutate(Installation $installation, array $body): void
    {
        $action = $body['action'] ?? null;
        if (!is_string($action) || !in_array($action, ['save', 'clear'], true)) {
            throw new InvalidArgumentException('Selecciona una acción válida.');
        }

        $guard = @fopen($installation->privatePath('install.guard'), 'c');
        if ($guard === false || !flock($guard, LOCK_EX | LOCK_NB)) {
            if (is_resource($guard)) {
                fclose($guard);
            }
            throw new DomainException('Hay otra actualización de configuración en curso.');
        }

        try {
            if ($installation->state() !== 'installed') {
                throw new RuntimeException('Installation state changed.');
            }

            $runtimePath = $installation->privatePath('runtime.json');
            $before = @file_get_contents($runtimePath);
            if ($before === false) {
                throw new RuntimeException('Private runtime unavailable.');
            }
            $runtime = json_decode($before, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($runtime)) {
                throw new RuntimeException('Invalid private runtime.');
            }

            if ($action === 'clear') {
                $runtime['MELI_CLIENT_ID'] = '';
                $runtime['MELI_CLIENT_SECRET'] = '';
            } else {
                $clientId = $body['client_id'] ?? null;
                $secret = $body['client_secret'] ?? null;
                if (!is_string($clientId) || !is_string($secret)) {
                    throw new InvalidArgumentException('Revisa Client ID y Client Secret.');
                }
                $clientId = trim($clientId);
                if ($clientId === '' || strlen($clientId) > 191 || strlen($secret) > 512) {
                    throw new InvalidArgumentException('Revisa Client ID y Client Secret.');
                }

                $currentClient = is_string($runtime['MELI_CLIENT_ID'] ?? null) ? $runtime['MELI_CLIENT_ID'] : '';
                $currentSecret = is_string($runtime['MELI_CLIENT_SECRET'] ?? null) ? $runtime['MELI_CLIENT_SECRET'] : '';
                if ($secret === '') {
                    if ($clientId !== $currentClient || $currentSecret === '') {
                        throw new InvalidArgumentException('Al cambiar el Client ID debes introducir un Client Secret nuevo.');
                    }
                    $secret = $currentSecret;
                }

                $runtime['MELI_CLIENT_ID'] = $clientId;
                $runtime['MELI_CLIENT_SECRET'] = $secret;
            }

            $encoded = json_encode($runtime, JSON_THROW_ON_ERROR);
            $installation->atomicWrite('runtime.json', $encoded);
            if ($installation->state() !== 'installed') {
                try {
                    $installation->atomicWrite('runtime.json', $before);
                } catch (Throwable) {
                    throw new RuntimeException('Private runtime rollback failed.');
                }
                throw new RuntimeException('Private runtime verification failed.');
            }
        } catch (\JsonException $exception) {
            throw new RuntimeException('Invalid private runtime.', previous: $exception);
        } finally {
            flock($guard, LOCK_UN);
            fclose($guard);
        }
    }
}
