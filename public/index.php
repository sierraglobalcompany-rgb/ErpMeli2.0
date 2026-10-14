<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Core\Install\Installation;
use App\Core\Install\WebInstaller;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$state = (new Installation($root))->state();
if ($state === 'invalid') {
    http_response_code(503);
    header('Cache-Control: no-store');
    exit('Configuración de instalación no disponible. Requiere revisión del operador.');
}
if ($state === 'fresh') {
    (new WebInstaller($root))->app()->run();
} else {
    Bootstrap::create()->run();
}
