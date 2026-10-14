<?php

declare(strict_types=1);

/** @var string $clientId */
/** @var bool $secretConfigured */
/** @var string $status */
/** @var string $oauthCallback */
/** @var string $webhookCallback */
/** @var string $csrfToken */
/** @var bool $serverManaged */
$e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mercado Libre — ERP Meli 2.0</title>
<style>
body{font:16px/1.5 system-ui,sans-serif;max-width:900px;margin:32px auto;padding:16px;color:#172839}nav{display:flex;gap:18px;flex-wrap:wrap}section{border:1px solid #ccd5dd;border-radius:8px;padding:18px;margin:18px 0}label{display:block;margin:10px 0}input,button{font:inherit;padding:8px}.muted{color:#526273}.danger{color:#9b241b}code{overflow-wrap:anywhere}
</style>
</head><body><main>
<nav><a href="/">Inicio</a><a href="/system/companies">Empresas</a><a href="/system/users">Usuarios</a><a href="/settings/system">Configuración del sistema</a><a href="/settings/mercadolibre">Mercado Libre</a></nav>
<h1>Aplicación Mercado Libre</h1>
<p>Configura aquí la aplicación Developer usada por esta instalación. Las cuentas vendedoras de cada empresa se vinculan en un paso posterior.</p>
<section>
<h2>Estado</h2>
<dl>
<dt>Configuración</dt><dd><?= $e($status) ?></dd>
<dt>Client ID</dt><dd><?= $clientId === '' ? 'No configurado' : $e($clientId) ?></dd>
<dt>Client Secret</dt><dd><?= $secretConfigured ? 'Secret configurado' : 'No configurado' ?></dd>
<dt>OAuth callback</dt><dd><code><?= $e($oauthCallback) ?></code></dd>
<dt>Webhook</dt><dd><code><?= $e($webhookCallback) ?></code></dd>
</dl>
<p class="muted">El Client Secret nunca se vuelve a mostrar. No lo pegues en chats, tickets, logs ni documentación.</p>
</section>
<?php if ($serverManaged): ?>
<section><h2>Administrada por servidor</h2><p>Las credenciales efectivas vienen del entorno del servidor. Para evitar una configuración dividida, este panel queda en modo de sólo lectura.</p></section>
<?php else: ?>
<section>
<h2>Guardar credenciales</h2>
<form method="post" action="/settings/mercadolibre">
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="action" value="save">
<label>Client ID <input name="client_id" maxlength="191" value="<?= $e($clientId) ?>" autocomplete="off" required></label>
<label>Client Secret <input name="client_secret" type="password" maxlength="512" value="" autocomplete="new-password"></label>
<p class="muted">Si el Client ID no cambia, puedes dejar el Secret vacío para conservar el actual. Si cambias el Client ID, debes introducir un Secret nuevo.</p>
<button type="submit">Guardar</button>
</form>
</section>
<section>
<h2>Limpiar configuración</h2>
<p>Esto sólo elimina las credenciales de la aplicación Developer. No elimina empresas, cuentas vendedoras ni datos históricos.</p>
<form method="post" action="/settings/mercadolibre">
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="action" value="clear">
<button class="danger" type="submit">Limpiar Client ID y Client Secret</button>
</form>
</section>
<?php endif; ?>
</main></body></html>
