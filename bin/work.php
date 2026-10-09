<?php

declare(strict_types=1);

use App\Core\Config\AppConfig;
use App\Core\Config\Environment;
use App\Core\Database\Connection;
use App\Integrations\MercadoLibre\Auth\OAuthRefreshService;
use App\Integrations\MercadoLibre\Auth\TokenCipher;
use App\Integrations\MercadoLibre\Client\ApiUsageRecorder;
use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Client\MeliCooldownRepository;
use App\Integrations\MercadoLibre\Transport\CurlMeliTransport;
use App\Integrations\MercadoLibre\Transport\RemoteHostPolicy;
use App\Modules\Sales\SyncOrder\OrderSyncWorkProcessor;
use App\Modules\Sales\SyncOrder\SyncOrderHandler;
use App\Modules\Settings\SystemSettingsRepository;
use App\Work\WorkExecution;
use App\Work\WorkRepository;
use App\Work\WorkRunner;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = AppConfig::fromEnvironment(Environment::all());
$pdo = Connection::fromConfig($config);
$runnerLockConnection = Connection::fromConfig($config);
$oauthLockConnection = Connection::fromConfig($config);

$settings = new SystemSettingsRepository($pdo);

/** @var array<string,array{method:string,path:string,family:string,classification:string,official_doc_url:string,verified_at:string}> $operations */
$operations = require dirname(__DIR__) . '/config/meli_operations.php';

$client = new MeliClient(
    new CurlMeliTransport(new RemoteHostPolicy(), $config->appEnv),
    $settings,
    $operations,
    'https://api.mercadolibre.com',
    new ApiUsageRecorder($pdo),
    new MeliCooldownRepository($pdo),
);

$tokens = new OAuthRefreshService(
    $pdo,
    $oauthLockConnection,
    $client,
    new TokenCipher($config->appKey),
    $config->meliClientId,
    $config->meliClientSecret,
    'erp_meli2.oauth.account',
);

$work = new WorkRepository($pdo);
$processor = new OrderSyncWorkProcessor(
    new SyncOrderHandler($work, $client, $tokens),
);
$execution = new WorkExecution(
    $settings,
    new WorkRunner($runnerLockConnection, 'erp_meli2.runner'),
    $work,
);

$processed = $execution->runAutomatic(
    $processor,
    maxItems: 25,
    maxSeconds: 45,
);

fwrite(STDOUT, 'processed=' . $processed . PHP_EOL);
