<?php

declare(strict_types=1);
/** @var string $step */
/** @var int $stage */
/** @var string $csrf */
/** @var string $error */
/** @var array<string,bool> $checks */
/** @var array<string,string> $data */
/** @var bool $databaseVerified */
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$titles = ['server'=>'Bienvenido a tu ERP','database'=>'Conecta tu base de datos','application'=>'Personaliza tu ERP','company'=>'Crea la empresa inicial','administrator'=>'Crea tu administrador','install'=>'Revisa antes de instalar','success'=>'Instalación completada'];
$help = ['server'=>'Prepararemos la configuración y tu empresa inicial. Primero comprobamos que este servidor pueda ejecutar el ERP.',
    'database'=>'Estos datos los entrega tu hosting o tu instalación local de MariaDB. Usa una base vacía y dedicada al ERP.',
    'application'=>'Elige el nombre que verás al ingresar y la dirección desde la que usarás el ERP.',
    'company'=>'El identificador es un nombre corto para reconocer a la empresa. Si lo dejas vacío, lo sugerimos a partir de su nombre.',
    'administrator'=>'Será administrador de la empresa inicial. Usa una contraseña de 12 a 72 bytes y conserva tus datos de acceso.',
    'install'=>'Comprueba estos datos. Al instalar se crearán las tablas y un único administrador; no se conectará a Mercado Libre.',
    'success'=>'ERP Meli 2.0 se instaló correctamente. Ya puedes iniciar sesión.'];
$fields = match ($step) {
    'database'=>$databaseVerified ? [] : ['DB_HOST'=>'Servidor de base de datos','DB_PORT'=>'Puerto','DB_NAME'=>'Nombre de la base de datos','DB_USER'=>'Usuario','DB_PASSWORD'=>'Contraseña'],
    'application'=>['APP_NAME'=>'Nombre del ERP','APP_URL'=>'Dirección del ERP','MELI_CLIENT_ID'=>'Client ID','MELI_CLIENT_SECRET'=>'Client Secret'],
    'company'=>['company_name'=>'Nombre de la empresa','company_slug'=>'Identificador'],
    'administrator'=>['display_name'=>'Nombre','username'=>'Usuario','email'=>'Correo de recuperación/contacto','password'=>'Contraseña','confirm_password'=>'Confirmar contraseña'],
    default=>[],
};
$mode = isset($data['APP_ENV']) ? ($data['APP_ENV'] === 'production' ? 'Producción' : 'Local') : 'Por determinar';
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalar ERP Meli 2.0</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f3f5f8;color:#172839;font:17px/1.55 system-ui,sans-serif}main{max-width:760px;margin:48px auto;padding:36px;background:white;border:1px solid #d9e0e7;border-radius:12px}h1{font-size:1.8rem;line-height:1.2}h2{font-size:1.15rem}.eyebrow{color:#445e73;font-size:.9rem}progress{width:100%;height:12px;accent-color:#176b67}label{display:block;margin:18px 0 6px;font-weight:600}input:not([type=hidden]):not([type=checkbox]){display:block;width:100%;font:inherit;padding:10px 12px;border:1px solid #7890a3;border-radius:6px}input:focus,button:focus,a:focus{outline:3px solid #7ed1c5;outline-offset:2px}button,.primary{font:inherit;padding:10px 18px;border-radius:6px;border:1px solid #176b67;background:#176b67;color:white;cursor:pointer}button.secondary{background:white;color:#176b67}button:disabled{opacity:.55;cursor:not-allowed}.actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}.notice{padding:14px;background:#e9f5ef;border-left:4px solid #176b67}.error{background:#fff0ed;border-left-color:#ad3a22}.muted,small{color:#455d70}dl{display:grid;grid-template-columns:1fr 2fr;gap:10px}dt{font-weight:600}dd{margin:0;overflow-wrap:anywhere}details{border:1px solid #d9e0e7;padding:16px;margin-top:20px}summary{cursor:pointer;font-weight:600}@media(max-width:600px){main{margin:12px;padding:22px}dl{grid-template-columns:1fr}dd{margin-bottom:12px}}
</style></head><body><main>
<p class="eyebrow">ERP Meli 2.0 · Configuración inicial</p>
<?php if ($step !== 'success'): ?><p>Paso <?= $stage+1 ?> de 6</p><progress max="6" value="<?= $stage+1 ?>" aria-label="Progreso de la instalación"></progress><?php endif; ?>
<h1><?= $escape($titles[$step]) ?></h1><p><?= $escape($help[$step]) ?></p>
<?php if ($error !== ''): ?><p role="alert" class="notice error"><?= $escape($error) ?></p><?php endif; ?>
<?php if ($step === 'success'): ?><p><a class="primary" href="/login">Iniciar sesión</a></p>
<?php else: ?>
<?php if ($step === 'server'): ?><ul>
<li>PHP: <?= $checks['php'] ? 'Compatible ✅' : 'Necesita una versión compatible' ?></li>
<li>PDO MySQL: <?= extension_loaded('pdo_mysql') ? 'Disponible ✅' : 'No disponible' ?></li>
<li>cURL: <?= extension_loaded('curl') ? 'Disponible ✅' : 'No disponible' ?></li>
<li>OpenSSL: <?= extension_loaded('openssl') ? 'Disponible ✅' : 'No disponible' ?></li>
<li>Storage privado: <?= $checks['storage'] ? 'Escribible ✅' : 'Revisa los permisos' ?></li>
<li>Otros requisitos: <?= $checks['extensions'] && $checks['vendor'] ? 'Disponibles ✅' : 'Faltan componentes requeridos' ?></li>
</ul><p class="muted">La base de datos se comprobará en el siguiente paso. Cuotas y cron se confirman con el proveedor de hosting.</p><?php endif; ?>
<?php if ($step === 'database' && $databaseVerified): ?><p class="notice">Conexión y permisos verificados. La base está vacía y es apta para instalar.</p><dl><dt>Servidor</dt><dd><?= $escape($data['DB_HOST']) ?></dd><dt>Base de datos</dt><dd><?= $escape($data['DB_NAME']) ?></dd><dt>Usuario</dt><dd><?= $escape($data['DB_USER']) ?></dd></dl><?php endif; ?>
<?php if ($step === 'install'): ?>
<dl><dt>ERP</dt><dd><?= $escape($data['APP_NAME']) ?></dd><dt>URL</dt><dd><?= $escape($data['APP_URL']) ?></dd><dt>Modo</dt><dd><?= $mode ?></dd><dt>Base de datos</dt><dd><?= $escape($data['DB_NAME']) ?></dd><dt>Empresa</dt><dd><?= $escape($data['company_name']) ?></dd><dt>Administrador</dt><dd><?= $escape($data['display_name']) ?></dd></dl>
<p>Mercado Libre: <?= ($data['MELI_CLIENT_ID'] ?? '') !== '' ? 'Configurado' : 'Pendiente' ?></p><p class="muted">Las contraseñas y claves se guardan en privado. Las escrituras remotas siguen desactivadas.</p>
<?php endif; ?>
<form method="post" action="/install" autocomplete="off">
<input type="hidden" name="csrf_token" value="<?= $escape($csrf) ?>"><input type="hidden" name="step" value="<?= $escape($step) ?>">
<?php foreach ($fields as $key=>$label): $secret=in_array($key,['DB_PASSWORD','MELI_CLIENT_SECRET','password','confirm_password'],true); ?>
<?php if ($key === 'MELI_CLIENT_ID'): ?><details><summary>Mercado Libre · Opcional</summary><p>Puedes configurarlo ahora o más adelante. Completa ambas credenciales o deja ambas vacías.</p><?php endif; ?>
<label for="<?= $escape($key) ?>"><?= $escape($label) ?></label><input id="<?= $escape($key) ?>" name="<?= $escape($key) ?>" type="<?= $secret ? 'password' : ($key==='email' ? 'email' : 'text') ?>" value="<?= $secret ? '' : $escape($data[$key] ?? ($key==='APP_NAME' ? 'ERP Meli 2.0' : ($key==='DB_PORT' ? '3306' : ''))) ?>" <?= in_array($key,['company_slug','DB_PASSWORD','MELI_CLIENT_ID','MELI_CLIENT_SECRET'],true) ? '' : 'required' ?>>
<?php if ($secret && $key !== 'confirm_password' && ($data[$key] ?? '') !== ''): ?><small>Configurado. El valor no se muestra.</small><?php endif; ?>
<?php if ($key==='MELI_CLIENT_SECRET'): ?>
<?php if (($data['MELI_CLIENT_SECRET'] ?? '') !== ''): ?><label><input type="checkbox" name="keep_meli" value="1"> Conservar la credencial configurada si no cambia el Client ID</label><?php endif; ?>
<?php if (isset($data['APP_URL'])): ?><p>Callback: <?= $escape($data['APP_URL']) ?>/oauth/mercadolibre/callback<br>Webhook: <?= $escape($data['APP_URL']) ?>/webhooks/mercadolibre</p><?php else: ?><small>Al guardar la dirección del ERP se mostrarán las direcciones de callback y webhook.</small><?php endif; ?></details>
<?php endif; ?>
<?php endforeach; ?>
<?php if ($step==='application'): ?><p>Modo detectado: <?= $mode ?>. Para una dirección pública se requiere HTTPS.</p><?php endif; ?>
<div class="actions">
<button name="action" value="<?= $step==='database' && !$databaseVerified ? 'probe' : 'continue' ?>" <?= $step==='server' && in_array(false,$checks,true) ? 'disabled' : '' ?>><?= $step==='install' ? 'Instalar ERP Meli 2.0' : ($step==='database' && !$databaseVerified ? 'Probar conexión' : 'Continuar') ?></button>
<?php if ($step==='database' && $databaseVerified): ?><button class="secondary" name="action" value="edit">Cambiar datos</button><?php endif; ?>
<?php if ($stage>0): ?><button class="secondary" name="action" value="back" formnovalidate>Volver</button><?php endif; ?>
</div></form>
<?php endif; ?></main></body></html>
