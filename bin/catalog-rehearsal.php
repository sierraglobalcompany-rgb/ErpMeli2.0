<?php

declare(strict_types=1);

use App\Core\Config\AppConfig;
use App\Core\Config\Environment;
use App\Core\Database\Connection;
use App\Modules\Catalog\ProductRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    $config = AppConfig::fromEnvironment(Environment::all());
    if ($config->appEnv !== 'test' || $config->dbHost !== '127.0.0.1' || $config->dbPort !== 3307
        || !in_array($config->dbName, ['erp_meli2_test', 'erp_meli2_rehearsal'], true)) {
        throw new RuntimeException('LOCAL_DISPOSABLE_RUNTIME_REQUIRED');
    }
    $options = getopt('', ['execute']);
    if ($options === false || !isset($argv) || count($argv) !== (isset($options['execute']) ? 2 : 1)) {
        throw new RuntimeException('USAGE_CATALOG_REHEARSAL_OPTIONAL_EXECUTE');
    }
    $base = dirname(__DIR__) . '/storage/exports/f13';
    $manifest = json_decode((string) file_get_contents($base . '/mapping-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($manifest) || !isset($manifest['catalog']) || !is_array($manifest['catalog'])) {
        throw new RuntimeException('CATALOG_MAPPING_INVALID');
    }
    $catalog = $manifest['catalog'];
    if (($catalog['classification'] ?? null) !== 'MIGRATE' || ($catalog['source_company_id'] ?? null) !== 3
        || ($catalog['ownership'] ?? null) !== 'PRESERVE_EXPLICIT_OWNER'
        || ($catalog['source_file'] ?? null) !== 'mapping-evidence/catalog-source.json'
        || !is_string($catalog['target_company_slug'] ?? null) || !is_string($catalog['source_sha256'] ?? null)) {
        throw new RuntimeException('CATALOG_MAPPING_INVALID');
    }
    $sourceFile = $base . '/mapping-evidence/catalog-source.json';
    if (!hash_equals($catalog['source_sha256'], (string) hash_file('sha256', $sourceFile))) {
        throw new RuntimeException('CATALOG_SOURCE_HASH_MISMATCH');
    }
    $source = json_decode((string) file_get_contents($sourceFile), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($source) || ($source['source_company_id'] ?? null) !== 3 || !is_array($source['products'] ?? null)
        || !array_is_list($source['products']) || count($source['products']) !== ($catalog['products'] ?? null)) {
        throw new RuntimeException('CATALOG_SOURCE_INVALID');
    }
    $rows = [];
    foreach ($source['products'] as $row) {
        if (!is_array($row) || count($row) !== 4 || !is_string($row['id'] ?? null)
            || !is_string($row['company_id'] ?? null) || !is_string($row['internal_sku'] ?? null) || !is_string($row['name'] ?? null)) {
            throw new RuntimeException('CATALOG_SOURCE_INVALID');
        }
        $rows[] = ['id' => $row['id'], 'company_id' => $row['company_id'], 'internal_sku' => $row['internal_sku'], 'name' => $row['name']];
    }
    $pdo = Connection::fromConfig($config);
    $target = $pdo->prepare('SELECT id FROM companies WHERE slug = :slug');
    $target->execute(['slug' => $catalog['target_company_slug']]);
    $id = $target->fetchColumn();
    if ($id === false) {
        throw new RuntimeException('CREATE_MAPPED_COMPANY_BEFORE_REHEARSAL');
    }
    $report = (new ProductRepository($pdo))->rehearseImport($rows, 3, (int) $id, $catalog['target_company_slug'], !isset($options['execute']));
    fwrite(STDOUT, json_encode($report, JSON_THROW_ON_ERROR) . PHP_EOL);
} catch (Throwable $e) {
    // Preserve diagnostics locally without echoing source rows, credentials or native DB messages.
    $code = $e instanceof PDOException ? 'LOCAL_DATABASE_FAILED' : ($e instanceof RuntimeException ? $e->getMessage() : 'CATALOG_REHEARSAL_FAILED');
    fwrite(STDERR, json_encode(['error' => $code], JSON_THROW_ON_ERROR) . PHP_EOL);
    exit(1);
}
