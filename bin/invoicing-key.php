<?php

declare(strict_types=1);

use App\Core\Config\AppConfig;
use App\Core\Config\Environment;
use App\Core\Database\Connection;
use App\Modules\Invoicing\InvoicingBridge;

require dirname(__DIR__) . '/vendor/autoload.php';

$usage = "Usage:\n"
    . "  php bin/invoicing-key.php issue COMPANY_ID [read|write|read,write]\n"
    . "  php bin/invoicing-key.php revoke KEY_ID\n";
$command = $argv[1] ?? '';

if (
    ($command !== 'issue' && $command !== 'revoke')
    || !isset($argv[2])
    || ($command === 'issue' && count($argv) > 4)
    || ($command === 'revoke' && count($argv) !== 3)
) {
    fwrite(STDERR, $usage);
    exit(2);
}

$companyId = null;
$keyId = null;
$scopes = [];
if ($command === 'issue') {
    $companyId = filter_var($argv[2], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!is_int($companyId)) {
        fwrite(STDERR, "Invalid company ID.\n");
        exit(2);
    }
    $requestedScopes = isset($argv[3]) ? explode(',', $argv[3]) : ['read', 'write'];
    $scopeMap = ['read' => 'invoicing.read', 'write' => 'invoicing.write'];
    foreach ($requestedScopes as $requestedScope) {
        $scope = $scopeMap[$requestedScope] ?? null;
        if ($scope === null || in_array($scope, $scopes, true)) {
            fwrite(STDERR, "Invalid or duplicate scope.\n");
            exit(2);
        }
        $scopes[] = $scope;
    }
} else {
    $keyId = filter_var($argv[2], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!is_int($keyId)) {
        fwrite(STDERR, "Invalid service key ID.\n");
        exit(2);
    }
}

try {
    $config = AppConfig::fromEnvironment(Environment::all());
    $bridge = new InvoicingBridge(
        Connection::fromConfig($config),
        dirname(__DIR__) . '/storage/invoices',
    );

    if ($command === 'issue') {
        $credential = $bridge->issueServiceKey($companyId, $scopes);
        fwrite(STDOUT, json_encode([
            'id' => $credential['id'],
            'scopes' => $credential['scopes'],
            'key' => $credential['key'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
        exit(0);
    }

    if (!$bridge->revokeServiceKey($keyId)) {
        throw new RuntimeException('Active service key was not found.');
    }
    fwrite(STDOUT, 'Revoked invoicing service key ' . $keyId . ".\n");
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
