<?php

declare(strict_types=1);

use App\Core\Config\AppConfig;
use App\Core\Config\Environment;
use App\Core\Database\Connection;
use App\Modules\Billing\BillingPeriodSyncHandler;

require dirname(__DIR__) . '/vendor/autoload.php';

$pdo = Connection::fromConfig(AppConfig::fromEnvironment(Environment::all()));
$processed = BillingPeriodSyncHandler::backfillReferences($pdo);

fwrite(STDOUT, 'Billing references indexed: ' . $processed . PHP_EOL);
