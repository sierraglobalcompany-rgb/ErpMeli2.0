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
$navigationCompany = null;
$navigationCompanies = [];
$activeCompanyId = $_SESSION['company_id'] ?? null;
if (is_int($activeCompanyId) && $activeCompanyId > 0) {
    $navigationCompany = [
        'id' => $activeCompanyId,
        'name' => 'Empresa activa',
        'role' => (string) ($_SESSION['role'] ?? ''),
    ];
    $navigationCompanies = [$navigationCompany];
}
$navigationIsSystemAdmin = true;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mercado Libre — ERP Meli 2.0</title>
</head>
<body>
<?php require dirname(__DIR__, 3) . '/Core/Http/views/navigation.php'; ?>
<main id="main-content" tabindex="-1" class="meli-app-settings-workspace" data-design-master-page="meli-app-settings">
    <header class="page-header">
        <span class="eyebrow">Administración global</span>
        <h1>Aplicación Mercado Libre</h1>
        <p>Configura aquí la aplicación Developer usada por esta instalación. Las cuentas vendedoras de cada empresa se vinculan en un paso posterior.</p>
    </header>

    <div class="settings-grid">
        <section class="panel panel-full meli-app-status-panel">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">Estado efectivo</span>
                    <h2>Aplicación Developer</h2>
                </div>
            </div>
            <dl class="settings-list">
                <dt>Configuración</dt><dd><?= $e($status) ?></dd>
                <dt>Client ID</dt><dd><?= $clientId === '' ? 'No configurado' : $e($clientId) ?></dd>
                <dt>Client Secret</dt><dd><?= $secretConfigured ? 'Secret configurado' : 'No configurado' ?></dd>
                <dt>OAuth callback</dt><dd><code><?= $e($oauthCallback) ?></code></dd>
                <dt>Webhook</dt><dd><code><?= $e($webhookCallback) ?></code></dd>
            </dl>
            <p class="muted">El Client Secret nunca se vuelve a mostrar. No lo pegues en chats, tickets, logs ni documentación.</p>
        </section>

        <?php if ($serverManaged): ?>
            <section class="panel panel-full meli-app-credentials-panel">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">Sólo lectura</span>
                        <h2>Administrada por servidor</h2>
                    </div>
                </div>
                <p>Las credenciales efectivas vienen del entorno del servidor. Para evitar una configuración dividida, este panel queda en modo de sólo lectura.</p>
            </section>
        <?php else: ?>
            <section class="panel meli-app-credentials-panel">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">Credenciales</span>
                        <h2>Guardar credenciales</h2>
                    </div>
                </div>
                <form class="settings-form" method="post" action="/settings/mercadolibre">
                    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                    <input type="hidden" name="action" value="save">
                    <label>
                        Client ID
                        <input name="client_id" maxlength="191" value="<?= $e($clientId) ?>" autocomplete="off" required>
                    </label>
                    <label>
                        Client Secret
                        <input name="client_secret" type="password" maxlength="512" value="" autocomplete="new-password">
                    </label>
                    <p class="muted">Si el Client ID no cambia, puedes dejar el Secret vacío para conservar el actual. Si cambias el Client ID, debes introducir un Secret nuevo.</p>
                    <button type="submit">Guardar</button>
                </form>
            </section>

            <section class="panel meli-app-clear-panel">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">Zona de riesgo</span>
                        <h2>Limpiar configuración</h2>
                    </div>
                </div>
                <details data-meli-app-destructive-action="clear-credentials">
                    <summary>Limpiar Client ID y Client Secret</summary>
                    <p class="muted">Se borrarán el Client ID y el Client Secret locales de la aplicación Developer hasta que vuelvas a configurarlos. No se eliminan empresas, cuentas vendedoras ni datos históricos.</p>
                    <form class="settings-form" method="post" action="/settings/mercadolibre">
                        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                        <input type="hidden" name="action" value="clear">
                        <label><input type="checkbox" name="confirm_clear_meli_credentials" value="1" required> Confirmo que deseo borrar las credenciales locales de Mercado Libre.</label>
                        <button class="danger" type="submit">Confirmar limpieza de credenciales</button>
                    </form>
                </details>
            </section>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
