<?php

declare(strict_types=1);

use App\Core\Config\AppConfig;
use App\Core\Config\Environment;
use App\Core\Database\Connection;
use App\Core\Logging\DebugMaintenance;
use App\Integrations\MercadoLibre\Client\ApiUsageRecorder;
use App\Modules\Settings\SystemSettingsRepository;
use App\Work\WorkRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = AppConfig::fromEnvironment(Environment::all());
$pdo = Connection::fromConfig($config);
$settings = (new SystemSettingsRepository($pdo))->get();
$now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

$maintenance = new DebugMaintenance(
    dirname(__DIR__) . '/storage/debug',
    dirname(__DIR__) . '/storage/exports',
);
$result = $maintenance->run($settings->debugRetentionDays, $now);
$deletedWork = (new WorkRepository($pdo))->purgeTerminalBefore($now->modify('-30 days'));
$deletedApiUsage = (new ApiUsageRecorder($pdo))->purgeBefore($now->modify('-90 days'));

fwrite(
    STDOUT,
    'compressed=' . $result['compressed']
    . ' deleted_debug=' . $result['deleted_debug']
    . ' deleted_exports=' . $result['deleted_exports']
    . ' deleted_work=' . $deletedWork
    . ' deleted_api_usage=' . $deletedApiUsage
    . PHP_EOL,
);
