<?php

declare(strict_types=1);

use App\Core\Config\AppConfig;
use App\Core\Config\Environment;
use App\Core\Database\Connection;
use App\Core\Database\Migrator;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = AppConfig::fromEnvironment(Environment::all());
$pdo = Connection::fromConfig($config);

(new Migrator())->migrate($pdo, dirname(__DIR__) . '/database/migrations');

fwrite(STDOUT, "Migrations complete.\n");
