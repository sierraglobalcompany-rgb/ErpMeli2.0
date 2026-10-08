<?php

declare(strict_types=1);

use App\Core\Config\AppConfig;
use App\Core\Config\Environment;
use App\Core\Database\Connection;
use App\Core\Runtime\RuntimePreflight;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = AppConfig::fromEnvironment(Environment::all());

try {
    $pdo = Connection::fromConfig($config);
    $report = (new RuntimePreflight())->inspect($config, $pdo, dirname(__DIR__));
} catch (Throwable $error) {
    $report = [
        'overall_status' => 'FAIL',
        'database' => [
            'status' => 'FAIL',
            'error' => $error->getMessage(),
        ],
    ];
}

$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
fwrite(STDOUT, $json . PHP_EOL);

exit($report['overall_status'] === 'FAIL' ? 1 : 0);
