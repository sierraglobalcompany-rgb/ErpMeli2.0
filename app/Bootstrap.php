<?php

declare(strict_types=1);

namespace App;

use App\Core\Config\AppConfig;
use App\Core\Config\Environment;
use App\Core\Http\Routes;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use Slim\Factory\AppFactory;

final class Bootstrap
{
    private const int MERCADOLIBRE_WEBHOOK_MAX_BODY_BYTES = 65_536;

    /** @return App<ContainerInterface|null> */
    public static function create(): App
    {
        $config = AppConfig::fromEnvironment(Environment::all());

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params([
                'httponly' => true,
                'secure' => $config->isProduction(),
                'samesite' => 'Lax',
            ]);
            session_start();
        }

        $app = AppFactory::create();
        $app->addBodyParsingMiddleware();

        // Added after BodyParsingMiddleware so Slim's LIFO middleware stack executes
        // this guard first. 64 KiB is an ERP2 implementation safety limit, not a
        // claimed Mercado Libre contractual limit.
        self::addMercadoLibreWebhookBodyGuard($app, $app->getResponseFactory());

        $app->addRoutingMiddleware();
        $app->addErrorMiddleware(false, true, true);

        Routes::register($app, $config);

        return $app;
    }

    /** @param App<ContainerInterface|null> $app */
    private static function addMercadoLibreWebhookBodyGuard(
        App $app,
        ResponseFactoryInterface $responseFactory,
    ): void {
        $app->add(static function (
            ServerRequestInterface $request,
            RequestHandlerInterface $handler,
        ) use ($responseFactory): ResponseInterface {
            if (
                $request->getMethod() !== 'POST'
                || $request->getUri()->getPath() !== '/webhooks/mercadolibre'
            ) {
                return $handler->handle($request);
            }

            $body = $request->getBody();
            $size = $body->getSize();
            if ($size !== null && $size > self::MERCADOLIBRE_WEBHOOK_MAX_BODY_BYTES) {
                return $responseFactory->createResponse(200);
            }

            if (!$body->isSeekable()) {
                return $size === null
                    ? $responseFactory->createResponse(200)
                    : $handler->handle($request);
            }

            $position = $body->tell();
            $body->rewind();
            $prefix = $body->read(self::MERCADOLIBRE_WEBHOOK_MAX_BODY_BYTES + 1);
            $body->seek($position);

            if (strlen($prefix) > self::MERCADOLIBRE_WEBHOOK_MAX_BODY_BYTES) {
                return $responseFactory->createResponse(200);
            }

            return $handler->handle($request);
        });
    }
}
