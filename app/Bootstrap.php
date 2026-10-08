<?php

declare(strict_types=1);

namespace App;

use App\Core\Config\AppConfig;
use App\Core\Config\Environment;
use App\Core\Http\Routes;
use Slim\App;
use Slim\Factory\AppFactory;

final class Bootstrap
{
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
        $app->addRoutingMiddleware();
        $app->addErrorMiddleware(false, true, true);

        Routes::register($app, $config);

        return $app;
    }
}
