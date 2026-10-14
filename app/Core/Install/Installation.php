<?php

declare(strict_types=1);

namespace App\Core\Install;

use App\Core\Config\AppConfig;
use App\Core\Config\Environment;
use RuntimeException;

final class Installation
{
    public function __construct(public readonly string $root) {}

    public function privatePath(string $name): string
    {
        return $this->root . '/storage/private/' . $name;
    }

    public static function urlMode(string $url): string
    {
        $parts = parse_url($url);
        if (filter_var($url, FILTER_VALIDATE_URL) === false || !is_array($parts) || !isset($parts['host'], $parts['scheme'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || !in_array($parts['scheme'], ['http','https'], true)) {
            throw new \InvalidArgumentException('Introduce una dirección válida, sin credenciales, parámetros ni fragmentos.');
        }
        $host = strtolower($parts['host']);
        if (filter_var($host, FILTER_VALIDATE_IP) === false && filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            throw new \InvalidArgumentException('El servidor de la dirección no es válido.');
        }
        if (in_array($host, ['localhost','127.0.0.1'], true)) { return 'local'; }
        if ($parts['scheme'] !== 'https') { throw new \InvalidArgumentException('En producción la dirección debe usar HTTPS.'); }
        return 'production';
    }

    public function state(): string
    {
        clearstatcache(true, $this->privatePath('installed.lock'));
        if (!is_file($this->privatePath('installed.lock'))) { return 'fresh'; }
        if (!is_file($this->privatePath('runtime.json')) || !is_readable($this->privatePath('runtime.json'))) { return 'invalid'; }
        try {
            $data = json_decode((string) @file_get_contents($this->privatePath('runtime.json')), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)) { return 'invalid'; }
            foreach (['APP_ENV','APP_NAME','APP_URL','APP_KEY','DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASSWORD','MELI_CLIENT_ID','MELI_CLIENT_SECRET'] as $key) {
                if (!isset($data[$key]) || ($key === 'DB_PORT' ? !is_int($data[$key]) && !is_string($data[$key]) : !is_string($data[$key]))) { return 'invalid'; }
            }
            foreach (['APP_NAME','DB_HOST','DB_NAME','DB_USER'] as $key) { if (trim($data[$key]) === '') { return 'invalid'; } }
            if (!is_string($data['APP_URL']) || self::urlMode($data['APP_URL']) !== $data['APP_ENV'] || !is_string($data['APP_KEY']) || strlen((string)base64_decode($data['APP_KEY'],true)) !== 32
                || filter_var($data['DB_PORT'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]) === false
                || (((string)$data['MELI_CLIENT_ID'] === '') !== ((string)$data['MELI_CLIENT_SECRET'] === ''))) { return 'invalid'; }
            $config = AppConfig::fromEnvironment(Environment::all($this->privatePath('runtime.json')));
            return $config->appKey !== '' && $config->dbName !== '' && $config->appName !== '' ? 'installed' : 'invalid';
        } catch (\Throwable) { return 'invalid'; }
    }

    public function atomicWrite(string $name, string $contents): void
    {
        $path = $this->privatePath($name);
        $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $handle = @fopen($temporary, 'x');
        if ($handle === false) { throw new RuntimeException('Private storage unavailable.'); }
        try {
            if (!@chmod($temporary, 0600) || @fwrite($handle, $contents) !== strlen($contents) || !@fflush($handle)) {
                throw new RuntimeException('Private write failed.');
            }
            if (function_exists('fsync') && !@fsync($handle)) { throw new RuntimeException('Private sync failed.'); }
            fclose($handle);
            $handle = null;
            if (!@rename($temporary, $path)) { throw new RuntimeException('Private commit failed.'); }
        } finally {
            if (is_resource($handle)) { fclose($handle); }
            if (is_file($temporary)) { unlink($temporary); }
        }
    }
}
