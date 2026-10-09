<?php

declare(strict_types=1);

use App\Core\Config\AppConfig;
use App\Core\Config\Environment;
use App\Core\Database\Connection;
use App\Core\Logging\DebugMaintenance;
use App\Modules\Settings\SystemSettingsRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = AppConfig::fromEnvironment(Environment::all());
$pdo = Connection::fromConfig($config);
$settings = (new SystemSettingsRepository($pdo))->get();

$maintenance = new DebugMaintenance(
    dirname(__DIR__) . '/storage/debug',
    dirname(__DIR__) . '/storage/exports',
);
$result = $maintenance->run($settings->debugRetentionDays);

fwrite(
    STDOUT,
    'compressed=' . $result['compressed']
    . ' deleted_debug=' . $result['deleted_debug']
    . ' deleted_exports=' . $result['deleted_exports']
    . PHP_EOL,
);
