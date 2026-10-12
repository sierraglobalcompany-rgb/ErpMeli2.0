<?php

declare(strict_types=1);

use App\Core\Auth\AuthService;
use App\Core\Config\AppConfig;
use App\Core\Config\Environment;
use App\Core\Database\Connection;
use App\Core\Database\Migrator;

require dirname(__DIR__) . '/vendor/autoload.php';

// Password never enters argv or output. Caller supplies and clears this environment value.
$password = getenv('ERP_BOOTSTRAP_PASSWORD');
putenv('ERP_BOOTSTRAP_PASSWORD');
unset($_ENV['ERP_BOOTSTRAP_PASSWORD'], $_SERVER['ERP_BOOTSTRAP_PASSWORD']);
if (PHP_SAPI !== 'cli' || !isset($argv) || count($argv) !== 4 || $password === false || $password === '') {
    fwrite(STDERR, "Usage: php bin/bootstrap-admin.php COMPANY_NAME COMPANY_SLUG ADMIN_EMAIL; set ERP_BOOTSTRAP_PASSWORD privately.\n");
    exit(1);
}
try {
    $config = AppConfig::fromEnvironment(Environment::all());
    $pdo = Connection::fromConfig($config);
    (new Migrator())->migrate($pdo, dirname(__DIR__) . '/database/migrations');
    (new AuthService($pdo))->bootstrapAdmin($argv[1], $argv[2], $argv[3], $password);
    fwrite(STDOUT, "First company/admin created. Login at /login.\n");
} catch (InvalidArgumentException|DomainException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, "Bootstrap failed; no partial authority committed. Check database configuration/migrations.\n");
    exit(1);
} finally {
    unset($password);
}
