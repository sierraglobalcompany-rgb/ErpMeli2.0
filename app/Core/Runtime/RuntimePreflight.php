<?php

declare(strict_types=1);

namespace App\Core\Runtime;

use App\Core\Config\AppConfig;
use PDO;

final class RuntimePreflight
{
    /** @var list<string> */
    private const REQUIRED_EXTENSIONS = [
        'curl',
        'json',
        'mbstring',
        'openssl',
        'pdo',
        'pdo_mysql',
        'phar',
        'session',
        'sodium',
        'zlib',
    ];

    /**
     * @return array{
     *   overall_status: string,
     *   php: array{status:string,version:string,sapi:string},
     *   extensions: array{status:string,missing:list<string>},
     *   database: array{status:string,version:string,sql_mode:string,time_zone:string},
     *   storage: array{status:string,unwritable:list<string>},
     *   disk: array{free_bytes:int,total_bytes:int},
     *   app_url: array{status:string,value:string},
     *   host_limits: array{status:string,note:string},
     *   cron_capacity: array{status:string,note:string}
     * }
     */
    public function inspect(AppConfig $config, PDO $pdo, string $projectRoot): array
    {
        $phpVersion = (string) phpversion();
        $phpStatus = version_compare($phpVersion, '8.3.0', '>=')
            && version_compare($phpVersion, '8.6.0', '<')
            ? 'PASS'
            : 'FAIL';

        $missing = [];
        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            if (!extension_loaded($extension)) {
                $missing[] = $extension;
            }
        }

        $databaseVersion = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        $sqlMode = (string) $pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn();
        $timeZone = (string) $pdo->query('SELECT @@SESSION.time_zone')->fetchColumn();

        $unwritable = [];
        foreach (['logs', 'debug', 'cache', 'exports', 'tmp'] as $directory) {
            $path = $projectRoot . '/storage/' . $directory;
            if (!is_dir($path) || !is_writable($path)) {
                $unwritable[] = 'storage/' . $directory;
            }
        }

        $free = disk_free_space($projectRoot);
        $total = disk_total_space($projectRoot);
        $freeBytes = $free === false ? 0 : (int) $free;
        $totalBytes = $total === false ? 0 : (int) $total;

        $httpsRequired = $config->isProduction();
        $httpsOk = str_starts_with(strtolower($config->appUrl), 'https://');
        $appUrlStatus = (!$httpsRequired || $httpsOk) ? 'PASS' : 'FAIL';

        $hardFailure = $phpStatus === 'FAIL'
            || $missing !== []
            || $databaseVersion === ''
            || $unwritable !== []
            || $freeBytes <= 0
            || $appUrlStatus === 'FAIL';

        return [
            'overall_status' => $hardFailure ? 'FAIL' : 'PARTIAL',
            'php' => [
                'status' => $phpStatus,
                'version' => $phpVersion,
                'sapi' => PHP_SAPI,
            ],
            'extensions' => [
                'status' => $missing === [] ? 'PASS' : 'FAIL',
                'missing' => $missing,
            ],
            'database' => [
                'status' => $databaseVersion !== '' ? 'PASS' : 'FAIL',
                'version' => $databaseVersion,
                'sql_mode' => $sqlMode,
                'time_zone' => $timeZone,
            ],
            'storage' => [
                'status' => $unwritable === [] ? 'PASS' : 'FAIL',
                'unwritable' => $unwritable,
            ],
            'disk' => [
                'free_bytes' => $freeBytes,
                'total_bytes' => $totalBytes,
            ],
            'app_url' => [
                'status' => $appUrlStatus,
                'value' => $config->appUrl,
            ],
            'host_limits' => [
                'status' => 'UNKNOWN',
                'note' => 'Disk, database and inode plan quotas must be confirmed in the hosting control panel.',
            ],
            'cron_capacity' => [
                'status' => 'UNKNOWN',
                'note' => 'Available cron slots and minimum interval must be confirmed in the hosting control panel.',
            ],
        ];
    }
}
