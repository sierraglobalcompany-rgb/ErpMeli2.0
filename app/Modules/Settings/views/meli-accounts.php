<?php

declare(strict_types=1);

/** @var array{id:int,name:string,role:string} $company */
/** @var list<array{id:int|string,external_user_id:string,site_id:string,nickname:?string,status:string,updated_at:string,can_hard_delete:bool}> $accounts */
/** @var list<array{id:int,name:string,role:string}> $companies */
/** @var string $csrfToken */
/** @var bool $appConfigured */
/** @var bool $systemAdmin */

$statusLabels = [
    'connected' => 'Conectada',
    'attention' => 'Requiere revisión',
    'reauth_required' => 'Debes volver a autorizar',
    'disabled' => 'Desconectada',
];
$statusClasses = [
    'connected' => 'status-connected',
    'attention' => 'status-attention',
    'reauth_required' => 'status-attention',
    'disabled' => 'status-disabled',
];
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Cuentas Mercado Libre</title></head>
<body>
<?php require dirname(__DIR__, 3) . '/Core/Http/views/navigation.php'; ?>
<main>
    <header class="page-header meli-accounts-page-header">
        <span class="eyebrow">Mercado Libre</span>
        <h1>Centro de conexión</h1>
        <p>Administra las cuentas vendedoras vinculadas a esta empresa y actúa sólo cuando una cuenta requiere atención.</p>
    </header>

    <div class="meli-accounts-workspace">
        <div class="settings-grid">
            <section class="panel meli-connection-panel" aria-labelledby="meli-account-help-title">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">Vinculación</span>
                        <h2 id="meli-account-help-title">Cuentas Mercado Libre</h2>
                    </div>
                </div>
                <p>Una empresa puede tener varias cuentas Mercado Libre.</p>
                <p>Cada cuenta Mercado Libre sólo puede estar vinculada a una empresa a la vez. El nickname de Mercado Libre no es la razón social de la empresa.</p>

                <div class="toolbar">
                    <?php if ($appConfigured): ?>
                        <a class="button" href="/oauth/mercadolibre/connect">Conectar otra cuenta Mercado Libre</a>
                    <?php else: ?>
                        <p class="notice" role="status">La aplicación Mercado Libre aún no está configurada. Contacta al Administrador del sistema.</p>
                        <?php if ($systemAdmin): ?><a class="button" href="/settings/mercadolibre">Configurar aplicación Mercado Libre</a><?php endif; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="panel panel-full meli-linked-accounts-panel" aria-labelledby="meli-accounts-title">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">Estado factual</span>
                        <h2 id="meli-accounts-title">Cuentas vinculadas</h2>
                    </div>
                    <p>Los estados y acciones se muestran según la cuenta real y tus permisos actuales.</p>
                </div>

                <?php if ($accounts === []): ?>
                    <p class="empty-state">No hay cuentas Mercado Libre vinculadas a esta empresa.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table data-responsive-table="meli-accounts">
                            <thead><tr><th>Cuenta</th><th>Seller ID</th><th>Sitio</th><th>Estado</th><th>Actualizada</th><th>Acciones</th></tr></thead>
                            <tbody>
                            <?php foreach ($accounts as $account): ?>
                                <?php $accountId = (int) $account['id']; ?>
                                <?php $statusClass = $statusClasses[$account['status']] ?? 'status-neutral'; ?>
                                <tr data-mobile-card="meli-account-row">
                                    <td data-label="Cuenta"><?= htmlspecialchars($account['nickname'] ?? 'Sin nickname', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Seller ID"><?= htmlspecialchars($account['external_user_id'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Sitio"><?= htmlspecialchars($account['site_id'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Estado"><span class="status-chip <?= $statusClass ?>"><?= htmlspecialchars($statusLabels[$account['status']] ?? 'Estado desconocido', ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td data-label="Actualizada"><?= htmlspecialchars($account['updated_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Acciones">
                                        <div class="row-actions">
                                            <?php if ($account['status'] === 'connected'): ?>
                                                <form method="post" action="/mercadolibre/accounts/<?= $accountId ?>/disconnect">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit">Desconectar</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($appConfigured && in_array($account['status'], ['attention', 'reauth_required', 'disabled'], true)): ?>
                                                <form method="post" action="/mercadolibre/accounts/<?= $accountId ?>/reauthorize">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit">Reautorizar</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($account['status'] === 'disabled' && $account['can_hard_delete']): ?>
                                                <form method="post" action="/mercadolibre/accounts/<?= $accountId ?>/delete">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button class="danger" type="submit">Eliminar</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>
</body>
</html>
