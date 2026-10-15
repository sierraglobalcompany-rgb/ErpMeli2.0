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
$passwordMin = \App\Core\Auth\PasswordService::MIN_LENGTH;
$titles = ['server'=>'Bienvenido a tu ERP','database'=>'Conecta tu base de datos','application'=>'Personaliza tu ERP','company'=>'Crea la empresa inicial','administrator'=>'Crea tu administrador','install'=>'Revisa antes de instalar','success'=>'Instalación completada'];
$help = ['server'=>'Prepararemos la configuración y tu empresa inicial. Primero comprobamos que este servidor pueda ejecutar el ERP.',
    'database'=>'Estos datos los entrega tu hosting o tu instalación local de MariaDB. Usa una base vacía y dedicada al ERP.',
    'application'=>'Elige el nombre que verás al ingresar y la dirección desde la que usarás el ERP.',
    'company'=>'El identificador es un nombre corto para reconocer a la empresa. Si lo dejas vacío, lo sugerimos a partir de su nombre.',
    'administrator'=>'Será administrador de la empresa inicial y del sistema. Usa una contraseña de '.$passwordMin.' a 72 bytes y conserva tus datos de acceso.',
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
:root{color-scheme:light;--ink:#17212b;--muted:#607080;--line:#dce4ea;--soft:#f4f7f9;--brand:#176b67;--brand-dark:#0e4f4c;--brand-soft:#e7f4f1;--danger:#ad3a22;--danger-soft:#fff0ed;--shadow:0 24px 60px rgba(31,47,61,.14)}
*{box-sizing:border-box}body{margin:0;color:var(--ink);font:16px/1.55 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:radial-gradient(circle at top right,#dcefeb 0,transparent 34%),linear-gradient(180deg,#eef4f5 0,#f7f9fb 46%,#eef2f5 100%);min-height:100vh}.install-page{min-height:100vh;padding:48px 20px}.install-shell{width:min(100%,900px);margin:0 auto}.install-brand{display:flex;align-items:center;gap:14px;margin:0 0 18px;padding:0 6px;color:var(--brand-dark)}.brand-mark{display:grid;place-items:center;width:42px;height:42px;border-radius:12px;background:var(--brand);color:white;font-weight:800;letter-spacing:-.04em;box-shadow:0 8px 20px rgba(23,107,103,.24)}.brand-copy{display:flex;flex-direction:column}.brand-copy strong{font-size:1rem}.brand-copy span{font-size:.82rem;color:var(--muted)}.install-card{background:#fff;border:1px solid rgba(206,217,225,.9);border-radius:20px;padding:38px 42px;box-shadow:var(--shadow)}.eyebrow{margin:0 0 8px;color:var(--brand);font-size:.78rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.step-line{display:flex;align-items:center;justify-content:space-between;gap:14px;margin:0 0 8px;color:var(--muted);font-size:.88rem;font-weight:650}progress{width:100%;height:8px;margin:0 0 28px;border:0;border-radius:999px;overflow:hidden;accent-color:var(--brand);background:#e8eef1}progress::-webkit-progress-bar{background:#e8eef1}progress::-webkit-progress-value{background:var(--brand);border-radius:999px}progress::-moz-progress-bar{background:var(--brand);border-radius:999px}h1{margin:0 0 10px;font-size:clamp(1.7rem,3vw,2.25rem);line-height:1.15;letter-spacing:-.03em}h1+p{margin-top:0;color:var(--muted);max-width:680px}ul{margin:24px 0;padding:0;list-style:none;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}li{padding:12px 14px;border:1px solid var(--line);border-radius:10px;background:var(--soft)}label{display:block;margin:20px 0 7px;font-weight:700;font-size:.94rem}input:not([type=hidden]):not([type=checkbox]){display:block;width:100%;font:inherit;padding:12px 14px;border:1px solid #aab8c2;border-radius:10px;background:#fff;color:var(--ink);transition:border-color .15s,box-shadow .15s}input:not([type=hidden]):not([type=checkbox]):hover{border-color:#8295a2}input:focus,button:focus,a:focus,summary:focus{outline:3px solid rgba(23,107,103,.25);outline-offset:2px}input:not([type=hidden]):not([type=checkbox]):focus{border-color:var(--brand);box-shadow:0 0 0 4px rgba(23,107,103,.08)}button,.primary{font:inherit;font-weight:750;padding:11px 18px;border-radius:10px;border:1px solid var(--brand);background:var(--brand);color:#fff;cursor:pointer;text-decoration:none;transition:transform .12s,background .12s,box-shadow .12s}button:hover,.primary:hover{background:var(--brand-dark);box-shadow:0 7px 18px rgba(23,107,103,.18)}button:active,.primary:active{transform:translateY(1px)}button.secondary{background:#fff;color:var(--brand-dark);border-color:#b5c6ca;box-shadow:none}button.secondary:hover{background:var(--brand-soft)}button:disabled{opacity:.55;cursor:not-allowed;box-shadow:none}.actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:30px;padding-top:24px;border-top:1px solid var(--line)}.notice{margin:20px 0;padding:14px 16px;background:var(--brand-soft);border:1px solid #cce6df;border-radius:10px;color:#164d49}.error{background:var(--danger-soft);border-color:#f0c6bc;color:var(--danger)}.muted,small{color:var(--muted)}small{display:block;margin-top:6px}dl{display:grid;grid-template-columns:minmax(140px,.8fr) 2fr;gap:0;margin:24px 0;border:1px solid var(--line);border-radius:12px;overflow:hidden}dt,dd{padding:12px 14px;border-bottom:1px solid var(--line)}dt{font-weight:700;background:var(--soft)}dd{margin:0;overflow-wrap:anywhere}dt:last-of-type,dd:last-of-type{border-bottom:0}details{border:1px solid var(--line);border-radius:12px;padding:16px 18px;margin-top:22px;background:#fbfcfd}summary{cursor:pointer;font-weight:750;color:var(--brand-dark)}details p{color:var(--muted)}form{margin-top:24px}.success-mark{display:grid;place-items:center;width:54px;height:54px;margin-bottom:18px;border-radius:16px;background:var(--brand-soft);color:var(--brand);font-size:1.55rem;font-weight:900}@media(max-width:700px){.install-page{padding:16px 10px 28px}.install-brand{padding:4px 4px 0}.install-card{padding:26px 20px;border-radius:16px}ul{grid-template-columns:1fr}dl{grid-template-columns:1fr}dt,dd{border-bottom:0}dd{border-bottom:1px solid var(--line)}.actions{flex-direction:column}.actions button,.actions .primary{width:100%;text-align:center}}
</style></head><body class="install-page"><main class="install-shell">
<header class="install-brand"><span class="brand-mark" aria-hidden="true">EM</span><span class="brand-copy"><strong>ERP Meli 2.0</strong><span>Configuración inicial segura</span></span></header>
<section class="install-card">
<p class="eyebrow">Configuración inicial</p>
<?php if ($step !== 'success'): ?><div class="step-line"><span>Paso <?= $stage+1 ?> de 6</span><span><?= $escape($titles[$step]) ?></span></div><progress max="6" value="<?= $stage+1 ?>" aria-label="Progreso de la instalación"></progress><?php endif; ?>
<?php if ($step === 'success'): ?><div class="success-mark" aria-hidden="true">✓</div><?php endif; ?>
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
<label for="<?= $escape($key) ?>"><?= $escape($label) ?></label><input id="<?= $escape($key) ?>" name="<?= $escape($key) ?>" type="<?= $secret ? 'password' : ($key==='email' ? 'email' : 'text') ?>" value="<?= $secret ? '' : $escape($data[$key] ?? ($key==='APP_NAME' ? 'ERP Meli 2.0' : ($key==='DB_HOST' ? 'localhost' : ($key==='DB_PORT' ? '3306' : '')))) ?>" <?= in_array($key,['password','confirm_password'],true) ? 'minlength="'.$passwordMin.'" maxlength="72"' : '' ?> <?= in_array($key,['company_slug','DB_PASSWORD','MELI_CLIENT_ID','MELI_CLIENT_SECRET'],true) ? '' : 'required' ?>>
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
<?php endif; ?></section></main></body></html>
